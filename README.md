# Petunjuk Praktikum 5 — Percobaan 4: Implementasi Endpoint HTTP PUT & DELETE (Full CRUD RESTful API)

## 1. Tujuan Pembelajaran
Fokus pada penyempurnaan REST API lengkap dengan penanganan HTTP PUT untuk pembaruan data dan DELETE untuk penghapusan record.

---

## 2. File Kerja
- Basis data: `database.sql`
- Konfigurasi PDO: `config/database.php`
- REST API Endpoint: `api/mahasiswa.php`
- HTTP Client Test: `test_api.http`
- Dashboard API & MySQL View: `index.php`

---

## 3. Cara Menjalankan Server PHP
Buka terminal pada direktori ini dan jalankan:
```bash
php -S localhost:8000
```
Buat database dan tabel dengan menjalankan `database.sql`, lalu buka `http://localhost:8000` atau gunakan berkas `test_api.http` untuk menguji endpoint.

## 4. Endpoint API
Endpoint `api/mahasiswa.php` mendukung:
- `GET` untuk mengambil semua mahasiswa, mencari dengan `?search=kata_kunci`, atau mengambil satu data dengan `?id=1`.
- `POST` untuk menambahkan data mahasiswa.
- `PUT` untuk memperbarui semua field (`npm`, `nama`, `program_studi`, `angkatan`, dan `email`) berdasarkan `?id=1`.
- `DELETE` untuk menghapus data berdasarkan `?id=1`.

Request `POST` dan `PUT` menerima objek JSON dengan semua field wajib. API mengembalikan `400` untuk input tidak valid, `404` jika ID tidak ditemukan, dan `409` jika NPM sudah digunakan.
