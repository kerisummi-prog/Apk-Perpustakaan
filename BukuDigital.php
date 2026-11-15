<?php
require_once __DIR__ . '/Buku.php';

/**
 * Kelas anak: BukuDigital (INHERITANCE dari Buku)
 * Atribut tambahan : formatFile, ukuranMB, linkUnduh, masaAkses, lisensi  (5 atribut)
 * Method           : unduh, perpanjangAkses, kurangiAkses, hitungDenda (override),
 *                    cekTersedia (override), getInfo (override), getJenis (override)
 */
class BukuDigital extends Buku
{
    private string $formatFile;
    private float $ukuranMB;
    private string $linkUnduh;
    private int $masaAkses;      // sisa hari akses
    private string $lisensi;

    public function __construct(
        string $kode,
        string $judul,
        string $penulis,
        int $tahunTerbit,
        int $stok,               // jumlah lisensi
        string $formatFile,
        float $ukuranMB,
        string $linkUnduh,
        int $masaAkses,
        string $lisensi
    ) {
        // memanggil konstruktor kelas induk
        parent::__construct($kode, $judul, $penulis, $tahunTerbit, $stok);
        $this->formatFile = $formatFile;
        $this->ukuranMB = $ukuranMB;
        $this->linkUnduh = $linkUnduh;
        $this->setMasaAkses($masaAkses);
        $this->lisensi = $lisensi;
    }

    // ---------- Getter & Setter ----------
    public function getFormatFile(): string { return $this->formatFile; }
    public function getUkuranMB(): float { return $this->ukuranMB; }
    public function getLinkUnduh(): string { return $this->linkUnduh; }
    public function getMasaAkses(): int { return $this->masaAkses; }
    public function getLisensi(): string { return $this->lisensi; }

    public function setMasaAkses(int $hari): void
    {
        $this->masaAkses = max(0, $hari);
    }

    // ---------- Behavior khusus ----------
    public function unduh(): string
    {
        if ($this->masaAkses <= 0) {
            return "Akses habis. Perpanjang dulu untuk mengunduh.";
        }
        return "Mengunduh {$this->judul}.{$this->formatFile} ({$this->ukuranMB} MB) dari {$this->linkUnduh}";
    }

    public function perpanjangAkses(int $hari): void
    {
        $this->setMasaAkses($this->masaAkses + $hari);
    }

    public function kurangiAkses(int $hari = 1): void
    {
        $this->setMasaAkses($this->masaAkses - $hari);
    }

    // ---------- Override (POLIMORFISME) ----------
    public function getJenis(): string
    {
        return "Buku Digital";
    }

    public function cekTersedia(): bool
    {
        // tersedia jika masih ada lisensi dan masa akses belum habis
        return parent::cekTersedia() && $this->masaAkses > 0;
    }

    public function getDendaPerHari(): int
    {
        return 0;
    }

    public function hitungHariTerlambat(int $hariPinjam): int
    {
        // buku digital tidak mengenal keterlambatan, akses otomatis berakhir
        return 0;
    }

    public function hitungDenda(int $hariPinjam): int
    {
        // buku digital tidak kena denda
        return 0;
    }

    public function getInfo(): string
    {
        return parent::getInfo()
            . " | {$this->formatFile}, {$this->ukuranMB} MB | Akses: {$this->masaAkses} hari | Lisensi: {$this->lisensi}";
    }
}
