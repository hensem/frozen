<?php
require 'auth_check.php';
require_approved();
require 'db.php';

header('Content-Type: text/html; charset=utf-8');

$now = date('Y-m-d H:i:s');
$msg = '';

// ── JSON list endpoint ───────────────────────────────────────────────────────
if (isset($_GET['list'])) {
  header('Content-Type: application/json');
  echo json_encode($pdo->query('SELECT id, name FROM templates ORDER BY name')->fetchAll(PDO::FETCH_ASSOC));
  exit;
}

// ── AJAX actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');

  $action = $_POST['action'];

  if ($action === 'create_template') {
    $name    = trim($_POST['name'] ?? '');
    $content = $_POST['content'] ?? '';
    if (!$name) { echo json_encode(['ok'=>false,'error'=>'Name required']); exit; }
    try {
      $pdo->prepare('INSERT INTO templates (name,content,created_at,updated_at) VALUES (?,?,?,?)')
          ->execute([$name, $content, $now, $now]);
      $id = $pdo->lastInsertId();
      log_activity($pdo, 'create_template', $name);
      echo json_encode(['ok'=>true,'id'=>(int)$id]);
    } catch (Exception $e) {
      echo json_encode(['ok'=>false,'error'=>'Name already exists']);
    }
    exit;
  }

  if ($action === 'save_template') {
    $id      = (int)($_POST['id'] ?? 0);
    $content = $_POST['content'] ?? '';
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'No template']); exit; }
    // Validate: find {{VAR}} in content, check all exist
    preg_match_all('/\{\{([A-Z0-9_]+)\}\}/', $content, $m);
    $used_names = array_unique($m[1]);
    $vars = $pdo->prepare('SELECT name FROM template_variables WHERE template_id=?');
    $vars->execute([$id]);
    $known = array_column($vars->fetchAll(PDO::FETCH_ASSOC), 'name');
    $undefined = array_diff($used_names, $known);
    if ($undefined) {
      echo json_encode(['ok'=>false,'error'=>'Undefined variables: {{'.implode('}}, {{', $undefined).'}}']);
      exit;
    }
    $pdo->prepare('UPDATE templates SET content=?,updated_at=? WHERE id=?')->execute([$content, $now, $id]);
    log_activity($pdo, 'save_template', "id=$id");
    echo json_encode(['ok'=>true]);
    exit;
  }

  if ($action === 'rename_template') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    if (!$id || !$name) { echo json_encode(['ok'=>false,'error'=>'Invalid']); exit; }
    try {
      $pdo->prepare('UPDATE templates SET name=?,updated_at=? WHERE id=?')->execute([$name, $now, $id]);
      log_activity($pdo, 'rename_template', "id=$id name=$name");
      echo json_encode(['ok'=>true]);
    } catch (Exception $e) {
      echo json_encode(['ok'=>false,'error'=>'Name already exists']);
    }
    exit;
  }

  if ($action === 'add_variable') {
    $tid   = (int)($_POST['template_id'] ?? 0);
    $name  = strtoupper(trim($_POST['name'] ?? ''));
    $value = $_POST['value'] ?? '';
    if (!$tid || !$name) { echo json_encode(['ok'=>false,'error'=>'Invalid']); exit; }
    if (!preg_match('/^[A-Z0-9_]+$/', $name)) {
      echo json_encode(['ok'=>false,'error'=>'Variable name must be uppercase letters, numbers and underscores only']);
      exit;
    }
    try {
      $pdo->prepare('INSERT INTO template_variables (template_id,name,value,created_at,updated_at) VALUES (?,?,?,?,?)')
          ->execute([$tid, $name, $value, $now, $now]);
      $vid = (int)$pdo->lastInsertId();
      // Propagate value to all items linked to this template
      $linked = $pdo->prepare('SELECT id FROM items WHERE template_id=?');
      $linked->execute([$tid]);
      $upsert = $pdo->prepare('
        INSERT INTO item_variable_values (item_id,template_variable_id,value,updated_at)
        VALUES (?,?,?,?)
        ON CONFLICT(item_id,template_variable_id) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at
      ');
      foreach ($linked->fetchAll(PDO::FETCH_COLUMN) as $iid) {
        $upsert->execute([$iid, $vid, $value, $now]);
      }
      log_activity($pdo, 'add_variable', "template_id=$tid name=$name");
      echo json_encode(['ok'=>true]);
    } catch (Exception $e) {
      echo json_encode(['ok'=>false,'error'=>'Variable already exists in this template']);
    }
    exit;
  }

  if ($action === 'rename_variable') {
    $vid      = (int)($_POST['var_id'] ?? 0);
    $new_name = strtoupper(trim($_POST['name'] ?? ''));
    $value    = $_POST['value'] ?? '';
    if (!$vid || !$new_name) { echo json_encode(['ok'=>false,'error'=>'Invalid']); exit; }
    if (!preg_match('/^[A-Z0-9_]+$/', $new_name)) {
      echo json_encode(['ok'=>false,'error'=>'Variable name must be uppercase letters, numbers and underscores only']);
      exit;
    }
    $var = $pdo->prepare('SELECT * FROM template_variables WHERE id=?');
    $var->execute([$vid]);
    $var = $var->fetch(PDO::FETCH_ASSOC);
    if (!$var) { echo json_encode(['ok'=>false,'error'=>'Variable not found']); exit; }
    $old_name = $var['name'];
    $tid      = $var['template_id'];
    try {
      $pdo->prepare('UPDATE template_variables SET name=?,value=?,updated_at=? WHERE id=?')->execute([$new_name, $value, $now, $vid]);
      $tpl = $pdo->prepare('SELECT content FROM templates WHERE id=?');
      $tpl->execute([$tid]);
      $content = $tpl->fetchColumn();
      $new_content = str_replace('{{'.$old_name.'}}', '{{'.$new_name.'}}', $content);
      $pdo->prepare('UPDATE templates SET content=?,updated_at=? WHERE id=?')->execute([$new_content, $now, $tid]);
      $linked = $pdo->prepare('SELECT id FROM items WHERE template_id=?');
      $linked->execute([$tid]);
      $upsert = $pdo->prepare('
        INSERT INTO item_variable_values (item_id,template_variable_id,value,updated_at)
        VALUES (?,?,?,?)
        ON CONFLICT(item_id,template_variable_id) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at
      ');
      foreach ($linked->fetchAll(PDO::FETCH_COLUMN) as $iid) {
        $upsert->execute([$iid, $vid, $value, $now]);
      }
      log_activity($pdo, 'edit_variable', "id=$vid $old_name->$new_name");
      echo json_encode(['ok'=>true]);
    } catch (Exception $e) {
      echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
  }

  if ($action === 'delete_variable') {
    $vid = (int)($_POST['var_id'] ?? 0);
    if (!$vid) { echo json_encode(['ok'=>false,'error'=>'Invalid']); exit; }
    $var = $pdo->prepare('SELECT tv.*, t.content FROM template_variables tv JOIN templates t ON t.id=tv.template_id WHERE tv.id=?');
    $var->execute([$vid]);
    $var = $var->fetch(PDO::FETCH_ASSOC);
    if (!$var) { echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }
    if (str_contains($var['content'], '{{'.$var['name'].'}}')) {
      echo json_encode(['ok'=>false,'error'=>'Variable is still used in template content. Remove all occurrences first.']);
      exit;
    }
    $pdo->prepare('DELETE FROM template_variables WHERE id=?')->execute([$vid]);
    log_activity($pdo, 'delete_variable', $var['name']);
    echo json_encode(['ok'=>true]);
    exit;
  }

  if ($action === 'load_template') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false]); exit; }
    $tpl = $pdo->prepare('SELECT * FROM templates WHERE id=?');
    $tpl->execute([$id]);
    $tpl = $tpl->fetch(PDO::FETCH_ASSOC);
    if (!$tpl) { echo json_encode(['ok'=>false]); exit; }
    $vars = $pdo->prepare('SELECT * FROM template_variables WHERE template_id=? ORDER BY name');
    $vars->execute([$id]);
    $vars = $vars->fetchAll(PDO::FETCH_ASSOC);
    foreach ($vars as &$v) {
      $v['used'] = str_contains($tpl['content'], '{{'.$v['name'].'}}');
    }
    echo json_encode(['ok'=>true,'template'=>$tpl,'variables'=>$vars]);
    exit;
  }

  if ($action === 'link') {
    $item_id     = (int)($_POST['item_id'] ?? 0);
    $template_id = ($_POST['template_id'] ?? '') === '' ? null : (int)$_POST['template_id'];
    if ($item_id) {
      $pdo->prepare('UPDATE items SET template_id=? WHERE id=?')->execute([$template_id, $item_id]);
      log_activity($pdo, 'link_template', "item_id=$item_id template_id=".($template_id ?? 'null'));
    }
    echo json_encode(['ok'=>true]);
    exit;
  }

  echo json_encode(['ok'=>false,'error'=>'Unknown action']);
  exit;
}

// ── Regular form POST: link item to template ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'link' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
  $item_id     = (int)($_POST['item_id'] ?? 0);
  $template_id = ($_POST['template_id'] ?? '') === '' ? null : (int)$_POST['template_id'];
  if ($item_id) {
    $pdo->prepare('UPDATE items SET template_id=? WHERE id=?')->execute([$template_id, $item_id]);
    log_activity($pdo, 'link_template', "item_id=$item_id template_id=".($template_id ?? 'null'));
  }
}

$templates = $pdo->query('SELECT id, name FROM templates ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$items     = $pdo->query('
  SELECT i.id, i.name, i.template_id, t.name AS template_name
  FROM items i
  LEFT JOIN templates t ON t.id = i.template_id
  ORDER BY CASE WHEN i.template_id IS NULL THEN 0 ELSE 1 END, i.name
')->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manual Templates — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
<style>
.tpl-wrap { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
@media(max-width:700px){ .tpl-wrap{grid-template-columns:1fr;} }
.panel { background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.panel h2 { margin-top:0; }
textarea#tpl-content { width:100%; min-height:320px; font-family:monospace; font-size:.9rem; padding:10px; border:1px solid #cbd5e1; border-radius:6px; resize:vertical; line-height:1.6; }
.var-row { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #f1f5f9; flex-wrap:wrap; }
.var-row:last-child { border-bottom:none; }
.var-name { font-family:monospace; font-size:.9rem; flex:1; min-width:120px; }
.badge-used { background:#dcfce7; color:#166534; padding:2px 8px; border-radius:12px; font-size:.75rem; }
.badge-unused { background:#f1f5f9; color:#64748b; padding:2px 8px; border-radius:12px; font-size:.75rem; }
.msg-box { padding:10px 14px; border-radius:6px; margin-bottom:14px; font-size:.9rem; display:none; }
.msg-ok  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
.msg-err { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
.field-row { display:flex; gap:8px; align-items:center; margin-bottom:12px; flex-wrap:wrap; }
.field-row input { flex:1; min-width:140px; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:.9rem; }
select.tpl-select { padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:.95rem; width:100%; }
.undefined-warn { background:#fff7ed; border:1px solid #fb923c; border-radius:6px; padding:8px 12px; font-size:.85rem; color:#9a3412; margin-top:8px; display:none; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Manual Templates</h1>

  <div style="margin-bottom:20px;">
    <label style="font-weight:600;display:block;margin-bottom:6px;">Template</label>
    <div class="field-row">
      <select class="tpl-select" id="tpl-select" style="max-width:320px;" onchange="loadTemplate(this.value)">
        <option value="">— Select Template —</option>
        <?php foreach ($templates as $t): ?>
        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div id="msg-global" class="msg-box"></div>

  <!-- Create new template -->
  <div class="panel" style="margin-bottom:24px;" id="panel-new">
    <h2>Add New Template</h2>
    <div class="field-row">
      <input type="text" id="new-tpl-name" placeholder="Template name">
      <button onclick="createTemplate()">Create</button>
    </div>
  </div>

  <!-- Editor (hidden until template selected) -->
  <div id="editor-area" style="display:none;">
    <div class="tpl-wrap">

      <!-- Left: content editor -->
      <div class="panel">
        <h2>Template Content</h2>
        <div class="field-row">
          <input type="text" id="tpl-name-input" placeholder="Template name">
          <button onclick="renameTemplate()">Rename</button>
        </div>
        <textarea id="tpl-content" placeholder="Enter template content..."></textarea>
        <div class="undefined-warn" id="undef-warn"></div>
        <div style="margin-top:10px;">
          <button onclick="saveTemplate()">Save Template</button>
        </div>
      </div>

      <!-- Right: variables -->
      <div class="panel">
        <h2>Variables</h2>
        <div id="var-list"></div>
        <div style="margin-top:14px;border-top:1px solid #f1f5f9;padding-top:14px;">
          <p style="font-size:.85rem;font-weight:600;margin-bottom:8px;">Add Variable</p>
          <div class="field-row">
            <input type="text" id="new-var-name" placeholder="e.g. MICROWAVE_MIN" style="text-transform:uppercase;" maxlength="30">
            <input type="text" id="new-var-value" placeholder="Value">
            <button onclick="addVariable()">Add</button>
          </div>
          <p style="font-size:.78rem;color:#94a3b8;margin-top:4px;">Uppercase letters, numbers and underscores only.</p>
        </div>
      </div>

    </div>
  </div>
  <!-- Item → Template linking -->
  <h2 style="margin-top:36px;">Link Items to Template</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Item</th><th>Current Template</th><th>Change To</th></tr></thead>
    <tbody>
    <?php foreach ($items as $item): ?>
      <tr>
        <td><?= htmlspecialchars($item['name']) ?></td>
        <td class="tpl-cell-<?= $item['id'] ?>"><?= $item['template_name'] ? htmlspecialchars($item['template_name']) : '<span class="muted">No template</span>' ?></td>
        <td>
          <div class="inline-form">
            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
            <select id="tpl-sel-<?= $item['id'] ?>" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:.88rem;">
              <option value="">— No template —</option>
              <?php foreach ($templates as $t): ?>
              <option value="<?= $t['id'] ?>" <?= $t['id'] == $item['template_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button onclick="linkTemplate(<?= $item['id'] ?>,this)">Save</button>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

</div>
<script>
let currentTplId = null;

function showMsg(msg, ok) {
  const el = document.getElementById('msg-global');
  el.className = 'msg-box ' + (ok ? 'msg-ok' : 'msg-err');
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 4000);
}

async function post(data) {
  const fd = new FormData();
  for (const k in data) fd.append(k, data[k]);
  const r = await fetch('/frozen/manual.php', { method:'POST', body:fd });
  if (r.status === 401) { location.href = '/frozen/home.php'; return {}; }
  const text = await r.text();
  try { return JSON.parse(text); }
  catch(e) { showMsg('Server error: ' + text.substring(0,200), false); return {}; }
}

async function loadTemplate(id) {
  if (!id) { document.getElementById('editor-area').style.display='none'; currentTplId=null; return; }
  const r = await post({ action:'load_template', id });
  if (!r.ok) { showMsg('Failed to load template.', false); return; }
  currentTplId = r.template.id;
  document.getElementById('tpl-name-input').value = r.template.name;
  document.getElementById('tpl-content').value    = r.template.content;
  document.getElementById('editor-area').style.display = 'block';
  renderVars(r.variables);
  checkUndefined();
}

function renderVars(vars) {
  const el = document.getElementById('var-list');
  if (!vars.length) { el.innerHTML = '<p class="muted">No variables yet.</p>'; return; }
  el.innerHTML = vars.map(v => `
    <div class="var-row" id="vrow-${v.id}">
      <span class="var-name">{{${v.name}}}</span>
      <span class="${v.used ? 'badge-used' : 'badge-unused'}">${v.used ? 'Used' : 'Unused'}</span>
      <button style="padding:4px 10px;font-size:.8rem;" data-vid="${v.id}" data-name="${escHtml(v.name)}" data-value="${escHtml(v.value||'')}" onclick="startEdit(this)">Edit</button>
      <button class="btn-danger" style="padding:4px 10px;font-size:.8rem;" onclick="deleteVar(${v.id},'${v.name}')" ${v.used ? 'disabled title="Remove from content first"' : ''}>Delete</button>
    </div>
  `).join('');
}

function checkUndefined() {
  const content = document.getElementById('tpl-content').value;
  const matches = [...content.matchAll(/\{\{([A-Z0-9_]+)\}\}/g)].map(m => m[1]);
  const varEls  = [...document.querySelectorAll('.var-name')].map(el => el.textContent.replace(/[{}]/g,''));
  const undef   = [...new Set(matches)].filter(n => !varEls.includes(n));
  const warn    = document.getElementById('undef-warn');
  if (undef.length) {
    warn.textContent = '⚠ Undefined variables: {{' + undef.join('}}, {{') + '}}';
    warn.style.display = 'block';
  } else {
    warn.style.display = 'none';
  }
}

document.addEventListener('input', e => { if (e.target.id === 'tpl-content') checkUndefined(); });
document.addEventListener('input', e => { if (e.target.id === 'new-var-name') e.target.value = e.target.value.toUpperCase(); });

async function createTemplate() {
  const name = document.getElementById('new-tpl-name').value.trim();
  if (!name) { showMsg('Enter a template name.', false); return; }
  const r = await post({ action:'create_template', name, content:'' });
  if (!r.ok) { showMsg(r.error, false); return; }
  showMsg('Template created.', true);
  document.getElementById('new-tpl-name').value = '';
  await reloadTemplateList(r.id);
}

async function reloadTemplateList(selectId) {
  const resp = await fetch('/frozen/manual.php?list=1');
  const data = await resp.json();
  const sel  = document.getElementById('tpl-select');
  sel.innerHTML = '<option value="">— Select Template —</option>' +
    data.map(t => `<option value="${t.id}" ${t.id==selectId?'selected':''}>${escHtml(t.name)}</option>`).join('');
  if (selectId) loadTemplate(selectId);
}

async function saveTemplate() {
  if (!currentTplId) return;
  const content = document.getElementById('tpl-content').value;
  const r = await post({ action:'save_template', id:currentTplId, content });
  showMsg(r.ok ? 'Template saved.' : r.error, r.ok);
}

async function renameTemplate() {
  if (!currentTplId) return;
  const name = document.getElementById('tpl-name-input').value.trim();
  if (!name) { showMsg('Enter a name.', false); return; }
  const r = await post({ action:'rename_template', id:currentTplId, name });
  if (!r.ok) { showMsg(r.error, false); return; }
  showMsg('Template renamed.', true);
  await reloadTemplateList(currentTplId);
}

async function addVariable() {
  if (!currentTplId) return;
  const name  = document.getElementById('new-var-name').value.trim().toUpperCase();
  const value = document.getElementById('new-var-value').value;
  if (!name) { showMsg('Enter a variable name.', false); return; }
  if (name.length > 30) { showMsg('Variable name cannot exceed 30 characters.', false); return; }
  if (!value) { showMsg('Enter a value.', false); return; }
  const r = await post({ action:'add_variable', template_id:currentTplId, name, value });
  if (!r.ok) { showMsg(r.error, false); return; }
  showMsg('Variable added.', true);
  document.getElementById('new-var-name').value  = '';
  document.getElementById('new-var-value').value = '';
  await loadTemplate(currentTplId);
}

async function startEdit(btn) {
  const vid      = btn.dataset.vid;
  const oldName  = btn.dataset.name;
  const oldValue = btn.dataset.value;
  const row  = document.getElementById('vrow-' + vid);
  const span = row.querySelector('.var-name');
  const orig = span.innerHTML;
  span.innerHTML = `<label style="font-size:.8rem;margin-right:2px;">Name</label><input type="text" value="${escHtml(oldName)}" maxlength="30" style="width:110px;padding:3px 6px;font-family:monospace;font-size:.88rem;text-transform:uppercase;" id="ri-${vid}"><br>
    <label style="font-size:.8rem;margin-right:2px;margin-top:4px;display:inline-block;">Value</label><input type="text" value="${escHtml(oldValue)}" placeholder="Value" style="width:110px;padding:3px 6px;font-size:.88rem;" id="rv-${vid}">`;
  btn.textContent = 'Save';
  const delBtn = row.querySelector('.btn-danger');
  const wasDisabled = delBtn.disabled;
  delBtn.disabled = false;
  delBtn.removeAttribute('title');
  delBtn.textContent = 'Cancel';
  delBtn.onclick = () => { span.innerHTML = orig; btn.textContent='Edit'; btn.onclick=()=>startEdit(btn); delBtn.textContent='Delete'; delBtn.disabled=wasDisabled; if(wasDisabled)delBtn.title='Remove from content first'; delBtn.onclick=()=>deleteVar(vid,oldName); };
  btn.onclick = async () => {
    const newName  = document.getElementById('ri-'+vid).value.trim().toUpperCase();
    const newValue = document.getElementById('rv-'+vid).value;
    if (!newName) return;
    if (newName.length > 30) { showMsg('Variable name cannot exceed 30 characters.', false); return; }
    if (!newValue) { showMsg('Enter a value.', false); return; }
    const r = await post({ action:'rename_variable', var_id:vid, name:newName, value:newValue });
    if (!r.ok) { showMsg(r.error, false); span.innerHTML = orig; btn.textContent='Edit'; btn.onclick=()=>startEdit(btn); delBtn.textContent='Delete'; delBtn.onclick=()=>deleteVar(vid,oldName); return; }
    showMsg('Variable saved.', true);
    await loadTemplate(currentTplId);
  };
}

async function deleteVar(vid, name) {
  if (!confirm('Delete variable {{' + name + '}}?')) return;
  const r = await post({ action:'delete_variable', var_id:vid });
  if (!r.ok) { showMsg(r.error, false); return; }
  showMsg('Variable deleted.', true);
  await loadTemplate(currentTplId);
}

async function linkTemplate(itemId, btn) {
  const template_id = document.getElementById('tpl-sel-' + itemId).value;
  const r = await post({ action:'link', item_id:itemId, template_id });
  showMsg(r.ok ? 'Template linked.' : (r.error || 'Error'), r.ok);
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>

