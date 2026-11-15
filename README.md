# Sistem Perpustakaan (PBO PHP)

Aplikasi UTS Pemrograman Berorientasi Objek.

## Struktur
```
perpustakaan/
├── index.php               # tampilan web + proses aksi
├── images/
│   ├── latar.jpg           # background (rak buku)
│   └── ilustrasi.jpg       # ilustrasi anime vintage (hero)
└── classes/
    ├── Buku.php            # kelas induk
    └── BukuDigital.php     # kelas anak (extends Buku)
```

## Cara menjalankan
Butuh PHP 7.4+ (disarankan 8.x).

```bash
cd perpustakaan
php -S localhost:8000
```
Lalu buka http://localhost:8000 di browser. (Atau taruh folder di `htdocs` XAMPP.)

## Pemenuhan ketentuan soal
| Ketentuan | Implementasi |
|---|---|
| 1 induk + 1 anak, min. 5 atribut & 4 method | `Buku` (5 atribut) dan `BukuDigital` (5 atribut tambahan) |
| Instansiasi objek | `new Buku(...)`, `new BukuDigital(...)` di `index.php` |
| Enkapsulasi | atribut `private`/`protected`, akses lewat getter/setter dengan validasi |
| Inheritance | `BukuDigital extends Buku`, `parent::__construct()`, override method |

| Perhitungan denda | Tanggal pinjam dicatat; `hitungHariTerlambat()` dan `hitungDenda()` di-override oleh `BukuDigital` (polimorfisme) |

**Aturan denda:** buku fisik batas 7 hari, denda Rp1.000 per hari keterlambatan. Buku digital tanpa denda.
Lama pinjam saat pengembalian terisi otomatis dari tanggal pinjam dan bisa diubah untuk simulasi.

Data disimpan di `$_SESSION` (tanpa database), jadi hilang saat sesi berakhir.
