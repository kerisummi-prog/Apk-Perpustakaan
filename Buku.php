<?php
/**
 * Kelas induk: Buku
 * Atribut : kode, judul, penulis, tahunTerbit, stok  (5 atribut)
 * Method  : pinjam, kembalikan, cekTersedia, hitungHariTerlambat, hitungDenda, getInfo, getter & setter
 */
class Buku
{
    // ENKAPSULASI: atribut private hanya bisa diakses lewat getter/setter
    private string $kode;
    private int $stok;

    // protected: bisa diakses kelas turunan
    protected string $judul;
    protected string $penulis;
    protected int $tahunTerbit;

    protected const DENDA_PER_HARI = 1000;
    protected const BATAS_PINJAM_HARI = 7;

    public function __construct(string $kode, string $judul, string $penulis, int $tahunTerbit, int $stok)
    {
        $this->kode = $kode;
        $this->judul = $judul;
        $this->penulis = $penulis;
        $this->setTahunTerbit($tahunTerbit);
        $this->setStok($stok);
    }

    // ---------- Getter ----------
    public function getKode(): string { return $this->kode; }
    public function getJudul(): string { return $this->judul; }
    public function getPenulis(): string { return $this->penulis; }
    public function getTahunTerbit(): int { return $this->tahunTerbit; }
    public function getStok(): int { return $this->stok; }

    // ---------- Setter (dengan validasi) ----------
    public function setStok(int $stok): void
    {
        // stok tidak boleh negatif
        $this->stok = max(0, $stok);
    }

    public function setTahunTerbit(int $tahun): void
    {
        $tahunIni = (int) date('Y');
        if ($tahun < 1000 || $tahun > $tahunIni) {
            throw new InvalidArgumentException("Tahun terbit tidak valid.");
        }
        $this->tahunTerbit = $tahun;
    }

    // ---------- Behavior ----------
    public function getJenis(): string
    {
        return "Buku Fisik";
    }

    public function cekTersedia(): bool
    {
        return $this->stok > 0;
    }

    public function pinjam(): bool
    {
        if ($this->cekTersedia()) {
            $this->stok--;
            return true;
        }
        return false;
    }

    public function kembalikan(): void
    {
        $this->stok++;
    }

    public function getBatasPinjamHari(): int
    {
        return static::BATAS_PINJAM_HARI;
    }

    public function getDendaPerHari(): int
    {
        return static::DENDA_PER_HARI;
    }

    // Jumlah hari keterlambatan = lama pinjam - batas pinjam (minimal 0)
    public function hitungHariTerlambat(int $hariPinjam): int
    {
        return max(0, $hariPinjam - static::BATAS_PINJAM_HARI);
    }

    // Denda = hari terlambat x denda per hari
    public function hitungDenda(int $hariPinjam): int
    {
        return $this->hitungHariTerlambat($hariPinjam) * static::DENDA_PER_HARI;
    }

    public function getInfo(): string
    {
        return "[{$this->kode}] {$this->judul} - {$this->penulis} ({$this->tahunTerbit}) | Stok: {$this->stok}";
    }
}
