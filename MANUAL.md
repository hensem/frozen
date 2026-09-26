# Manual Pengguna — Sistem Frozen

Sistem pengurusan jualan makanan beku. Digunakan untuk menguruskan stok, rekod jualan, pesanan online, dan templat manual produk.

---

## Senarai Kandungan

1. [Log Masuk](#1-log-masuk)
2. [Dashboard](#2-dashboard)
3. [Items — Pengurusan Stok](#3-items--pengurusan-stok)
4. [Sell — Rekod Jualan](#4-sell--rekod-jualan)
5. [History — Sejarah Jualan](#5-history--sejarah-jualan)
6. [Images — Pengurusan Gambar](#6-images--pengurusan-gambar)
7. [Manual Templates](#7-manual-templates)
8. [Orders — Pesanan Online](#8-orders--pesanan-online)
9. [Products — Senarai Produk](#9-products--senarai-produk)
10. [Users — Pengurusan Pengguna](#10-users--pengurusan-pengguna)
11. [Halaman Awam](#11-halaman-awam)

---

## 1. Log Masuk

- Buka `https://frozen.kuceng.my`
- Klik **Staff's Login** di penjuru kanan atas
- Log masuk menggunakan akaun Google
- Akaun baru perlu menunggu kelulusan admin sebelum boleh menggunakan sistem
- Selepas diluluskan, anda akan dibawa terus ke Dashboard

---

## 2. Dashboard

**URL:** `/frozen/`

Halaman utama selepas log masuk. Menunjukkan ringkasan perniagaan dan kawalan lokasi.

### Lokasi Semasa

- Pilih lokasi dari senarai dropdown dan klik **Update** untuk mengemas kini lokasi yang dipaparkan kepada pelanggan
- Untuk tambah lokasi baru, masukkan nama lokasi dan URL Google Maps, kemudian klik **Add**
- Lokasi semasa akan dipaparkan di halaman awam (`home.php`)

### Kad Ringkasan

| Kad | Penerangan |
|-----|-----------|
| Total Revenue | Jumlah hasil jualan keseluruhan |
| Total Profit | Jumlah keuntungan keseluruhan |
| Out of Stock | Bilangan item yang habis stok |

- Item yang habis stok akan disenaraikan dalam amaran berwarna kuning

### Jadual Keuntungan per Item

Menunjukkan setiap item dengan maklumat: stok semasa, harga beli, harga jual, unit terjual, hasil, dan keuntungan.

---

## 3. Items — Pengurusan Stok

**URL:** `/frozen/items`

### Tambah Item Baru

Isi borang dengan maklumat berikut:
- **Name** — Nama item (mesti unik)
- **Quantity** — Kuantiti stok awal
- **Buy Price (RM)** — Harga beli
- **Sell Price (RM)** — Harga jual

Klik **Add Item** untuk simpan.

### Kemaskini Item

Setiap item dalam jadual boleh diedit terus:
- Tukar nilai **Stock**, **Buy Price**, atau **Sell Price**
- Klik **Update** pada baris berkenaan

Item yang habis stok (kuantiti = 0) akan ditandakan dengan warna merah.

### Jana PDF

Dua butang tersedia di atas jadual:

| Butang | Penerangan |
|--------|-----------|
| ⬇️ Senarai Harga (BM) | PDF senarai harga dalam Bahasa Melayu (2 lajur: Item, Harga). Halaman kedua mengandungi kod QR. |
| ⬇️ Stock List (EN) | PDF senarai stok dalam Bahasa Inggeris (3 lajur: Item, Stock, lajur kosong). |

---

## 4. Sell — Rekod Jualan

**URL:** `/frozen/sell`

Digunakan untuk merekodkan jualan secara manual (jualan terus / bukan online).

### Cara Rekod Jualan

1. Pilih **Item** dari senarai dropdown (hanya item yang ada stok dipaparkan)
2. Masukkan **Quantity** yang dijual
3. Pratonton keuntungan akan dipaparkan secara automatik
4. Klik **Confirm Sale** untuk simpan

Sistem akan:
- Menolak kuantiti dari stok item
- Merekodkan transaksi dalam jadual `sales`
- Memaparkan keuntungan jualan tersebut

> Jika kuantiti yang dimasukkan melebihi stok, sistem akan menolak transaksi.

---

## 5. History — Sejarah Jualan

**URL:** `/frozen/history`

Laporan jualan dengan penapisan tarikh. Tiga paparan tersedia:

### Penapisan Tarikh

Pilih tarikh **From** dan **To**, kemudian klik **Filter**. Klik **Reset** untuk kembali ke tetapan asal.

### Paparan: Report

Laporan lengkap merangkumi:

- **Summary** — Jumlah jualan, kos, keuntungan, margin, unit terjual, bilangan transaksi, purata jualan per transaksi
- **Product Performance** — Prestasi setiap produk: kuantiti terjual, jualan, kos, keuntungan, margin, stok baki, sell-through %
- **Best Sellers** — Produk terlaris mengikut kuantiti
- **Highest-Profit Products** — Produk dengan keuntungan tertinggi
- **Slow-Moving Products** — Produk dengan sell-through < 50% atau tiada jualan, dengan cadangan tindakan:
  - **Restock** — Stok habis
  - **Monitor** — Perlu dipantau
  - **Reduce / Stop** — Pertimbangkan untuk kurangkan atau hentikan
- **Sales by Day** — Jualan mengikut hari
- **Sales by Hour** — Jualan mengikut jam
- **Transaction History** — Senarai semua transaksi

> Klik pada tajuk lajur dalam mana-mana jadual untuk mengisih data.

### Paparan: By Day

Ringkasan jualan harian: unit terjual, hasil, dan keuntungan.

### Paparan: By Transaction

Senarai setiap transaksi individu dengan butiran lengkap.

---

## 6. Images — Pengurusan Gambar

**URL:** `/frozen/images`

### Muat Naik Gambar Baru

1. Masukkan **Name** untuk gambar
2. Pilih fail gambar (maksimum **100KB**)
3. Klik **Upload**

Format yang diterima: semua format imej (`image/*`).

### Urus Gambar

- **Cari** gambar menggunakan kotak carian
- **Namakan semula** gambar dengan mengedit nama dalam jadual dan klik **Save**
- **Padam** gambar dengan klik **Delete** — item yang menggunakan gambar tersebut akan kembali ke gambar lalai
- Gambar lalai (ID 1) tidak boleh dipadam

### Kaitkan Gambar dengan Item

Di bahagian **Link Items to Image**:
1. Cari item yang ingin dikaitkan
2. Pilih gambar dari dropdown (boleh taip untuk cari)
3. Klik **Link**

---

## 7. Manual Templates

**URL:** `/frozen/manual`

Templat manual digunakan untuk memaparkan arahan atau maklumat produk kepada pelanggan di halaman awam. Nama item akan menjadi pautan yang boleh diklik untuk membuka manual.

### Aliran Kerja

```
Buat Templat → Tambah Pembolehubah / Gambar → Tulis Kandungan → Simpan → Kaitkan dengan Item
```

### Buat Templat Baru

1. Masukkan nama templat dalam kotak **Add New Template**
2. Klik **Create**

### Edit Kandungan Templat

1. Pilih templat dari dropdown **Template**
2. Edit kandungan dalam kotak teks
3. Gunakan `{{NAMA_PEMBOLEHUBAH}}` untuk sisipkan nilai pembolehubah
4. Gunakan `[[NAMA_GAMBAR]]` untuk sisipkan gambar
5. Klik **Save Template**

> Sistem akan memberi amaran jika terdapat pembolehubah atau gambar yang digunakan dalam kandungan tetapi belum ditakrifkan.

### Pembolehubah

Pembolehubah membolehkan nilai yang berbeza digunakan dalam templat yang sama.

**Tambah Pembolehubah:**
1. Masukkan nama (huruf besar, nombor, dan `_` sahaja, maksimum 30 aksara) — contoh: `MICROWAVE_MIN`
2. Masukkan nilai
3. Klik **Add**

**Edit Pembolehubah:**
- Klik **Edit** pada baris pembolehubah
- Ubah nama atau nilai
- Klik **Save**
- Klik **Cancel** untuk batal

**Padam Pembolehubah:**
- Pembolehubah yang masih digunakan dalam kandungan (`{{NAMA}}`) tidak boleh dipadam
- Buang dahulu dari kandungan, kemudian padam

Badge **Used** (hijau) atau **Unused** (kelabu) menunjukkan sama ada pembolehubah digunakan dalam kandungan.

### Gambar dalam Templat

**Tambah Gambar:**
1. Masukkan nama token (huruf besar, nombor, dan `_` sahaja, maksimum 30 aksara) — contoh: `CARA_MASAK`
2. Taip dalam kotak carian untuk cari gambar dari pustaka
3. Pilih gambar dari senarai yang muncul
4. Klik **Add**

Gunakan `[[CARA_MASAK]]` dalam kandungan templat untuk memaparkan gambar tersebut.

**Padam Gambar:**
- Gambar yang masih digunakan dalam kandungan (`[[NAMA]]`) tidak boleh dipadam
- Buang dahulu dari kandungan, kemudian padam

### Kaitkan Item dengan Templat

Di bahagian **Link Items to Template** (bawah halaman):
1. Cari item dalam jadual
2. Pilih templat dari dropdown
3. Klik **Save**

Pilih **— No template —** untuk buang kaitan.

### Namakan Semula Templat

1. Pilih templat
2. Ubah nama dalam kotak **Template name**
3. Klik **Rename**

---

## 8. Orders — Pesanan Online

**URL:** `/frozen/orders`

Menguruskan pesanan yang dibuat oleh pelanggan melalui halaman awam (`/frozen/order.php`).

### Penapisan Status

| Tab | Penerangan |
|-----|-----------|
| Pending | Pesanan baru yang belum diproses |
| Completed | Pesanan yang telah selesai |
| Cancelled | Pesanan yang dibatalkan |
| All | Semua pesanan |

Bilangan pesanan **Pending** dipaparkan sebagai lencana merah di navigasi.

### Butiran Pesanan

Setiap kad pesanan menunjukkan:
- Nama, nombor telefon, alamat, e-mel pelanggan
- Tarikh dan masa pesanan
- Senarai item, kuantiti, harga, dan jumlah
- Jumlah keseluruhan

### Tindakan

**Pesanan Pending:**
- Klik **✓ Completed** — tandakan sebagai selesai
- Klik **✕ Cancel** — batalkan pesanan

**Pesanan Completed / Cancelled:**
- Klik **↩ Set Pending** — kembalikan ke status pending

---

## 9. Products — Senarai Produk

**URL:** `/frozen/products`

Senarai produk borong/runcit untuk rujukan dalaman. Berbeza daripada **Items** — ini adalah katalog produk, bukan stok jualan.

### Cari Produk

Taip dalam kotak **Search** untuk menapis senarai produk secara langsung.

### Kemaskini via CSV

Format CSV: `name,pcs,wholesale_price,retail_price`

- Baris pertama adalah header (akan dilangkau)
- `retail_price` boleh dikosongkan
- Sistem akan **tambah** produk baru, **kemaskini** produk sedia ada jika ada perubahan, dan **abaikan** jika tiada perubahan

Klik **Upload** selepas pilih fail CSV.

---

## 10. Users — Pengurusan Pengguna

**URL:** `/frozen/users`

Hanya admin yang boleh mengurus pengguna.

### Status Pengguna

| Status | Penerangan |
|--------|-----------|
| Pending | Baru daftar, menunggu kelulusan |
| Approved | Diluluskan, boleh akses sistem |
| Banned | Diharamkan, tidak boleh log masuk |

### Tindakan

| Tindakan | Penerangan |
|----------|-----------|
| Approve | Luluskan pengguna baru |
| Unapprove | Tarik balik kelulusan |
| Ban | Haramkan pengguna |
| Unban | Benarkan semula dan luluskan |

> Pengguna tidak boleh mengubah status diri sendiri. Akaun admin utama tidak boleh di-unapprove atau di-ban.

### Log Aktiviti

Rekod semua tindakan yang dilakukan oleh semua pengguna. Boleh ditapis mengikut:
- **Pengguna** tertentu
- **Tarikh** dari dan hingga

Navigasi halaman tersedia jika rekod melebihi 20 entri.

---

## 11. Halaman Awam

Halaman-halaman berikut boleh diakses tanpa log masuk:

### Senarai Harga (`/frozen/home.php` atau `/frozen/`)

- Memaparkan senarai item yang ada stok beserta harga
- Gambar item boleh diklik untuk paparan penuh
- Nama item yang mempunyai manual boleh diklik untuk membuka arahan produk
- Lokasi semasa dan pautan Google Maps dipaparkan di bawah
- Pautan ke halaman Order Online

### Order Online (`/frozen/order.php`)

Borang pesanan untuk pelanggan:
1. Isi nama, nombor telefon, alamat, dan e-mel (pilihan)
2. Pilih item dan kuantiti
3. Klik **Hantar Pesanan**

Pelanggan akan menerima e-mel pengesahan pesanan. Maklumat penghantaran (Self Collect, Lalamove, dan maklumat pembayaran) dipaparkan di bawah borang.

### Produk Awam (`/frozen/pub_products.php`)

Senarai produk untuk tatapan umum.

---

*Manual ini adalah untuk kegunaan dalaman sahaja.*
