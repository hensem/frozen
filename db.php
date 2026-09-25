<?php
require_once __DIR__ . '/../../config/config_frozen.php';

$pdo = new PDO('sqlite:' . __DIR__ . '/../../config/frozen.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA journal_mode=WAL');

// Migrations (run before table creation so existing DBs get updates)
try { $pdo->exec('ALTER TABLE orders ADD COLUMN status TEXT NOT NULL DEFAULT \'pending\''); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE items ADD COLUMN template_id INTEGER REFERENCES templates(id) ON DELETE SET NULL'); } catch (Exception $e) {}

$pdo->exec("
CREATE TABLE IF NOT EXISTS images (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  data BLOB NOT NULL,
  mime_type TEXT NOT NULL,
  created_at TEXT NOT NULL,
  created_by INTEGER,
  updated_by INTEGER
);
CREATE TABLE IF NOT EXISTS items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  quantity INTEGER NOT NULL DEFAULT 0,
  buy_price REAL NOT NULL DEFAULT 0,
  sell_price REAL NOT NULL DEFAULT 0,
  image_id INTEGER NOT NULL DEFAULT 1,
  created_by INTEGER,
  updated_by INTEGER
);
CREATE TABLE IF NOT EXISTS sales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER NOT NULL,
  quantity_sold INTEGER NOT NULL,
  buy_price REAL NOT NULL,
  sell_price REAL NOT NULL,
  sold_at TEXT NOT NULL,
  FOREIGN KEY(item_id) REFERENCES items(id)
);
CREATE TABLE IF NOT EXISTS restocks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER NOT NULL,
  quantity INTEGER NOT NULL,
  buy_price REAL NOT NULL,
  sell_price REAL NOT NULL,
  restocked_at TEXT NOT NULL,
  FOREIGN KEY(item_id) REFERENCES items(id)
);
CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  pcs INTEGER NOT NULL DEFAULT 1,
  wholesale_price REAL NOT NULL,
  retail_price REAL,
  created_at TEXT,
  updated_at TEXT,
  created_by INTEGER,
  updated_by INTEGER
);

CREATE TABLE IF NOT EXISTS templates (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  content TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  created_by INTEGER,
  updated_by INTEGER
);
CREATE TABLE IF NOT EXISTS template_variables (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  template_id INTEGER NOT NULL REFERENCES templates(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  created_by INTEGER,
  updated_by INTEGER,
  UNIQUE(template_id, name)
);
CREATE TABLE IF NOT EXISTS item_variable_values (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
  template_variable_id INTEGER NOT NULL REFERENCES template_variables(id) ON DELETE CASCADE,
  value TEXT NOT NULL DEFAULT '',
  updated_at TEXT NOT NULL,
  created_by INTEGER,
  updated_by INTEGER,
  UNIQUE(item_id, template_variable_id)
);
CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  phone TEXT NOT NULL,
  address TEXT NOT NULL,
  email TEXT,
  items TEXT NOT NULL,
  total REAL NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  ordered_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS locations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  last_used TEXT,
  map_url TEXT,
  created_by INTEGER,
  updated_by INTEGER
);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  google_id TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  email TEXT NOT NULL,
  avatar TEXT,
  approved INTEGER NOT NULL DEFAULT 0,
  banned INTEGER NOT NULL DEFAULT 0,
  deleted INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS activity_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  action TEXT NOT NULL,
  detail TEXT,
  created_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id)
);
");

// Migrations (post-table-creation, safe to run on existing DBs)
try { $pdo->exec("ALTER TABLE template_variables ADD COLUMN value TEXT NOT NULL DEFAULT ''"); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE images ADD COLUMN created_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE images ADD COLUMN updated_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE items ADD COLUMN created_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE items ADD COLUMN updated_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE templates ADD COLUMN created_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE templates ADD COLUMN updated_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE template_variables ADD COLUMN created_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE template_variables ADD COLUMN updated_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE item_variable_values ADD COLUMN created_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE item_variable_values ADD COLUMN updated_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE locations ADD COLUMN created_by INTEGER'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE locations ADD COLUMN updated_by INTEGER'); } catch (Exception $e) {}
