<?php
// Class harus di-load SEBELUM session_start agar objek di session bisa di-unserialize
require_once __DIR__ . '/classes/Buku.php';
require_once __DIR__ . '/classes/BukuDigital.php';

session_start();

// Folder gambar (ubah jika file gambar diletakkan di tempat lain, mis. '' untuk folder yang sama dengan index.php)
$dirGambar = 'images/';

// ---------- INSTANSIASI OBJEK (class -> object) ----------
if (!isset($_SESSION['koleksi'])) {
    $_SESSION['koleksi'] = [
        'B001' => new Buku('B001', 'Pemrograman PHP untuk Pemula', 'Andi Wijaya', 2020, 3),
        'B002' => new Buku('B002', 'Dasar-Dasar OOP', 'Siti Rahma', 2019, 2),
        'D001' => new BukuDigital('D001', 'Belajar Laravel', 'Budi Santoso', 2022, 5,
                    'PDF', 12.5, 'https://example.com/laravel.pdf', 14, 'Single User'),
        'D002' => new BukuDigital('D002', 'Basis Data Modern', 'Rina Kusuma', 2021, 2,
                    'EPUB', 4.8, 'https://example.com/basisdata.epub', 7, 'Multi User'),
    ];
    $_SESSION['pinjaman'] = [];   // buku yang sedang dipinjam
    $_SESSION['riwayat'] = [];    // riwayat transaksi
    $_SESSION['pesan'] = '';
}
$_SESSION['total_denda'] = $_SESSION['total_denda'] ?? 0;   // akumulasi denda yang sudah dibayar

function rupiah(int $n): string { return 'Rp' . number_format($n, 0, ',', '.'); }
// Lama pinjam sejak tanggal pinjam (dibulatkan ke atas, minimal 1 hari)
function hariBerjalan(int $tgl): int { return max(1, (int) ceil((time() - $tgl) / 86400)); }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function catat(string $teks): void
{
    array_unshift($_SESSION['riwayat'], date('H:i:s') . ' - ' . $teks);
}

// ---------- PROSES AKSI ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $kode = $_POST['kode'] ?? '';
    $koleksi = &$_SESSION['koleksi'];

    try {
        switch ($aksi) {
            case 'tambah':
                $kode = strtoupper(trim($_POST['kode_baru'] ?? ''));
                if ($kode === '' || isset($koleksi[$kode])) {
                    throw new InvalidArgumentException('Kode kosong atau sudah dipakai.');
                }
                $judul = trim($_POST['judul'] ?? '');
                $penulis = trim($_POST['penulis'] ?? '');
                $tahun = (int) ($_POST['tahun'] ?? 0);
                $stok = (int) ($_POST['stok'] ?? 0);
                if ($judul === '' || $penulis === '') {
                    throw new InvalidArgumentException('Judul dan penulis wajib diisi.');
                }
                if (($_POST['jenis'] ?? '') === 'digital') {
                    $koleksi[$kode] = new BukuDigital($kode, $judul, $penulis, $tahun, $stok,
                        trim($_POST['format'] ?? 'PDF'), (float) ($_POST['ukuran'] ?? 1),
                        trim($_POST['link'] ?? '#'), (int) ($_POST['akses'] ?? 7),
                        trim($_POST['lisensi'] ?? 'Single User'));
                } else {
                    $koleksi[$kode] = new Buku($kode, $judul, $penulis, $tahun, $stok);
                }
                $_SESSION['pesan'] = "Buku $kode berhasil ditambahkan.";
                catat("Tambah buku $kode");
                break;

            case 'pinjam':
                $peminjam = trim($_POST['peminjam'] ?? '');
                if ($peminjam === '') {
                    throw new InvalidArgumentException('Nama peminjam wajib diisi.');
                }
                $buku = $koleksi[$kode] ?? null;
                if ($buku && $buku->pinjam()) {
                    $_SESSION['pinjaman'][] = ['kode' => $kode, 'peminjam' => $peminjam, 'tgl' => time()];
                    $_SESSION['pesan'] = "$peminjam meminjam \"{$buku->getJudul()}\". Batas {$buku->getBatasPinjamHari()} hari.";
                    catat("$peminjam meminjam $kode");
                } else {
                    throw new RuntimeException('Buku tidak tersedia.');
                }
                break;

            case 'kembali':
                $idx = (int) ($_POST['idx'] ?? -1);
                $hari = max(1, (int) ($_POST['hari'] ?? 1));
                if (!isset($_SESSION['pinjaman'][$idx])) {
                    throw new RuntimeException('Data pinjaman tidak ditemukan.');
                }
                $p = $_SESSION['pinjaman'][$idx];
                $buku = $koleksi[$p['kode']];
                $buku->kembalikan();
                $terlambat = $buku->hitungHariTerlambat($hari);   // polimorfisme
                $denda = $buku->hitungDenda($hari);               // polimorfisme
                array_splice($_SESSION['pinjaman'], $idx, 1);
                $_SESSION['total_denda'] += $denda;

                $rincian = $denda > 0
                    ? "Terlambat $terlambat hari x " . rupiah($buku->getDendaPerHari()) . " = " . rupiah($denda)
                    : ($buku instanceof BukuDigital ? "Buku digital tidak dikenai denda." : "Tepat waktu, tidak ada denda.");
                $_SESSION['pesan'] = "{$p['peminjam']} mengembalikan \"{$buku->getJudul()}\" setelah $hari hari. $rincian";
                catat("{$p['peminjam']} mengembalikan {$p['kode']} ($hari hari, denda " . rupiah($denda) . ")");
                break;

            case 'unduh':
                $buku = $koleksi[$kode] ?? null;
                if ($buku instanceof BukuDigital) {
                    $_SESSION['pesan'] = $buku->unduh();
                    catat("Unduh $kode");
                }
                break;

            case 'perpanjang':
                $buku = $koleksi[$kode] ?? null;
                if ($buku instanceof BukuDigital) {
                    $buku->perpanjangAkses(7);
                    $_SESSION['pesan'] = "Akses {$buku->getJudul()} diperpanjang 7 hari.";
                    catat("Perpanjang akses $kode");
                }
                break;

            case 'kurangi':
                $buku = $koleksi[$kode] ?? null;
                if ($buku instanceof BukuDigital) {
                    $sebelum = $buku->getMasaAkses();
                    $buku->kurangiAkses(7);   // tidak bisa kurang dari 0
                    $berkurang = $sebelum - $buku->getMasaAkses();
                    $_SESSION['pesan'] = $berkurang > 0
                        ? "Akses {$buku->getJudul()} dikurangi $berkurang hari. Sisa {$buku->getMasaAkses()} hari."
                        : "Akses {$buku->getJudul()} sudah habis, tidak bisa dikurangi lagi.";
                    catat("Kurangi akses $kode ($berkurang hari)");
                }
                break;

            case 'reset':
                session_destroy();
                header('Location: index.php');
                exit;
        }
    } catch (Throwable $e) {
        $_SESSION['pesan'] = 'Gagal: ' . $e->getMessage();
    }
    header('Location: index.php');
    exit;
}

$pesan = $_SESSION['pesan'];
$_SESSION['pesan'] = '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sistem Perpustakaan (PBO PHP)</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --tinta: #0e1311;
    --kaca: rgba(20, 26, 23, .62);
    --kaca-kuat: rgba(20, 26, 23, .80);
    --kertas: #f0e8cf;
    --redup: #b3ae97;
    --lampu: #f0c24b;
    --garis: rgba(240, 232, 207, .14);
    --bahaya: #d0644d;
    --hijau: #8fb58a;
  }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: system-ui, "Segoe UI", sans-serif; color: var(--kertas); background: var(--tinta); line-height: 1.55; }

  /* Latar: rak buku */
  body::before { content: ""; position: fixed; inset: 0; z-index: -2;
    background: url('<?= $dirGambar ?>latar.jpg') center / cover no-repeat; }
  body::after { content: ""; position: fixed; inset: 0; z-index: -1;
    background: radial-gradient(ellipse at 80% 0%, rgba(240, 194, 75, .16), transparent 55%),
                linear-gradient(180deg, rgba(8, 11, 10, .45), rgba(8, 11, 10, .82)); }

  .wadah { max-width: 1040px; margin: 0 auto; padding: 0 18px; }

  /* Hero */
  .hero { padding: 56px 0 28px; }
  .hero h1 { font-family: Lora, Georgia, serif; font-weight: 600; font-size: clamp(38px, 7vw, 64px); line-height: 1.05; margin: 0 0 10px; }
  .hero p { margin: 0 0 18px; font-size: 18px; color: var(--kertas); max-width: 36ch; }
  .statistik { display: flex; flex-wrap: wrap; gap: 8px; }

  .chip { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 13px; background: rgba(240, 232, 207, .10); border: 1px solid var(--garis); }
  .chip.ok { background: rgba(143, 181, 138, .18); border-color: rgba(143, 181, 138, .4); }
  .chip.habis { background: rgba(208, 100, 77, .18); border-color: rgba(208, 100, 77, .4); }

  /* Panel */
  .panel { background: var(--kaca); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
    border: 1px solid var(--garis); border-radius: 24px; padding: 24px; margin-bottom: 22px; }
  .panel h2 { font-family: Lora, Georgia, serif; font-weight: 600; font-size: 24px; margin: 0 0 16px; }

  .pesan { background: rgba(240, 194, 75, .14); border: 1px solid rgba(240, 194, 75, .45); padding: 12px 18px; margin-bottom: 22px; border-radius: 16px; }

  /* Kartu buku */
  .rak { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 16px; }
  .buku { background: var(--kaca-kuat); border: 1px solid var(--garis); border-radius: 20px; padding: 18px; display: flex; flex-direction: column; gap: 10px;
    transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease; }
  .buku:hover { transform: translateY(-3px); border-color: rgba(240, 194, 75, .5); box-shadow: 0 12px 30px rgba(0, 0, 0, .35); }
  .buku .atas { display: flex; justify-content: space-between; align-items: center; }
  .buku .kode { color: var(--redup); font-size: 13px; }
  .buku h3 { font-family: Lora, Georgia, serif; font-weight: 500; font-size: 19px; line-height: 1.3; margin: 0; }
  .buku .penulis { margin: -4px 0 0; color: var(--redup); font-size: 14px; }
  .chips { display: flex; flex-wrap: wrap; gap: 6px; }
  .aksi { margin-top: auto; display: flex; flex-direction: column; gap: 8px; padding-top: 6px; }

  .badge { padding: 3px 12px; border-radius: 999px; font-size: 12px; color: #fff; }
  .fisik { background: #7a7358; }
  .digital { background: #3f7594; }

  /* Daftar pinjaman */
  .pinjam { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
    background: var(--kaca-kuat); border: 1px solid var(--garis); border-radius: 16px; padding: 12px 16px; margin-bottom: 10px; }
  .pinjam small { display: block; }

  /* Form */
  input, select, button { padding: 9px 14px; font-size: 14px; font-family: inherit; }
  input, select { background: rgba(255, 255, 255, .07); color: var(--kertas); border: 1px solid var(--garis); border-radius: 999px; min-width: 0; }
  input::placeholder { color: var(--redup); }
  select option { background: #1a211e; color: var(--kertas); }
  button { background: var(--lampu); color: #1d1606; font-weight: 600; border: 0; border-radius: 999px; cursor: pointer; transition: transform .15s ease, filter .15s ease; }
  button:hover { filter: brightness(1.08); transform: translateY(-1px); }
  button.tipis { background: transparent; color: var(--kertas); border: 1px solid var(--garis); }
  button.tipis:hover { border-color: var(--lampu); }
  button.merah { background: var(--bahaya); color: #fff; }
  :focus-visible { outline: 2px solid var(--lampu); outline-offset: 2px; }

  form.baris { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
  form.baris input { flex: 1 1 120px; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-bottom: 14px; }
  .khusus-digital { display: none; }
  .grid.tampil-digital .khusus-digital { display: block; }

  small, .redup { color: var(--redup); }
  ul.riwayat { margin: 0 0 16px; padding-left: 20px; }

  @media (max-width: 640px) {
    .hero { padding-top: 36px; }
    .panel { padding: 18px; border-radius: 20px; }
  }
  @media (prefers-reduced-motion: reduce) {
    * { transition: none !important; }
  }
</style>
</head>
<body>
<div class="wadah">

<header class="hero">
  <h1>Perpustakaan</h1>
  <p>Pinjam, kembalikan, dan unduh buku dari satu rak.</p>
  <div class="statistik">
    <span class="chip"><?= count($_SESSION['koleksi']) ?> judul buku</span>
    <span class="chip"><?= count($_SESSION['pinjaman']) ?> sedang dipinjam</span>
    <span class="chip <?= $_SESSION['total_denda'] > 0 ? 'habis' : '' ?>">Total denda <?= rupiah($_SESSION['total_denda']) ?></span>
  </div>
</header>

<?php if ($pesan !== ''): ?>
  <div class="pesan"><?= h($pesan) ?></div>
<?php endif; ?>

<section class="panel">
  <h2>Koleksi Buku</h2>
  <div class="rak">
    <?php foreach ($_SESSION['koleksi'] as $b): ?>
    <article class="buku">
      <div class="atas">
        <span class="badge <?= $b instanceof BukuDigital ? 'digital' : 'fisik' ?>"><?= h($b->getJenis()) ?></span>
        <span class="kode"><?= h($b->getKode()) ?></span>
      </div>
      <h3><?= h($b->getJudul()) ?></h3>
      <p class="penulis"><?= h($b->getPenulis()) ?>, <?= $b->getTahunTerbit() ?></p>
      <div class="chips">
        <span class="chip <?= $b->getStok() > 0 ? 'ok' : 'habis' ?>">Stok <?= $b->getStok() ?></span>
        <?php if ($b instanceof BukuDigital): ?>
          <span class="chip"><?= h($b->getFormatFile()) ?>, <?= $b->getUkuranMB() ?> MB</span>
          <span class="chip">Akses <?= $b->getMasaAkses() ?> hari</span>
          <span class="chip"><?= h($b->getLisensi()) ?></span>
        <?php endif; ?>
      </div>
      <div class="aksi">
        <?php if ($b->cekTersedia()): ?>
        <form method="post" class="baris">
          <input type="hidden" name="aksi" value="pinjam">
          <input type="hidden" name="kode" value="<?= h($b->getKode()) ?>">
          <input name="peminjam" placeholder="Nama peminjam" required>
          <button>Pinjam</button>
        </form>
        <?php else: ?>
          <small>Tidak tersedia saat ini</small>
        <?php endif; ?>
        <?php if ($b instanceof BukuDigital): ?>
        <form method="post" class="baris">
          <input type="hidden" name="kode" value="<?= h($b->getKode()) ?>">
          <button class="tipis" name="aksi" value="unduh">Unduh</button>
          <button class="tipis" name="aksi" value="perpanjang">Perpanjang 7 hari</button>
          <button class="tipis" name="aksi" value="kurangi">Kurangi 7 hari</button>
        </form>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <h2>Sedang Dipinjam</h2>
  <?php if (empty($_SESSION['pinjaman'])): ?>
    <p class="redup">Belum ada peminjaman. Pilih buku di atas untuk mulai meminjam.</p>
  <?php else: ?>
    <?php foreach ($_SESSION['pinjaman'] as $i => $p):
        $b = $_SESSION['koleksi'][$p['kode']];
        $tgl = $p['tgl'] ?? time();
        $berjalan = hariBerjalan($tgl);
        $tempo = $tgl + $b->getBatasPinjamHari() * 86400;
        $telat = $b->hitungHariTerlambat($berjalan);
        $estimasi = $b->hitungDenda($berjalan);
    ?>
    <div class="pinjam">
      <div>
        <strong><?= h($p['peminjam']) ?></strong>
        <small><?= h($b->getJudul()) ?></small>
        <small>Dipinjam <?= date('d/m/Y', $tgl) ?>, jatuh tempo <?= date('d/m/Y', $tempo) ?> (batas <?= $b->getBatasPinjamHari() ?> hari)</small>
        <div class="chips" style="margin-top:6px">
          <?php if ($b instanceof BukuDigital): ?>
            <span class="chip">Tanpa denda</span>
          <?php elseif ($telat > 0): ?>
            <span class="chip habis">Terlambat <?= $telat ?> hari, denda <?= rupiah($estimasi) ?></span>
          <?php else: ?>
            <span class="chip ok">Hari ke-<?= $berjalan ?>, belum terlambat</span>
          <?php endif; ?>
        </div>
      </div>
      <form method="post" class="baris">
        <input type="hidden" name="aksi" value="kembali">
        <input type="hidden" name="idx" value="<?= $i ?>">
        <input type="number" name="hari" min="1" value="<?= $berjalan ?>" style="width:80px; flex:none" aria-label="Lama pinjam (hari)">
        <span class="redup">hari</span>
        <button>Kembalikan</button>
      </form>
    </div>
    <?php endforeach; ?>
    <small>Lama pinjam terisi otomatis dari tanggal pinjam. Ubah angkanya untuk mensimulasikan keterlambatan. Buku fisik: <?= rupiah(1000) ?>/hari setelah batas. Buku digital: tanpa denda.</small>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Tambah Buku</h2>
  <form method="post">
    <input type="hidden" name="aksi" value="tambah">
    <div class="grid" id="formTambah">
      <input name="kode_baru" placeholder="Kode (mis. B003)" required>
      <input name="judul" placeholder="Judul" required>
      <input name="penulis" placeholder="Penulis" required>
      <input name="tahun" type="number" placeholder="Tahun terbit" required>
      <input name="stok" type="number" placeholder="Stok / lisensi" required>
      <select name="jenis" id="jenis" aria-label="Jenis buku">
        <option value="fisik">Buku Fisik</option>
        <option value="digital">Buku Digital</option>
      </select>
      <input class="khusus-digital" name="format" placeholder="Format (PDF, EPUB)">
      <input class="khusus-digital" name="ukuran" type="number" step="0.1" placeholder="Ukuran (MB)">
      <input class="khusus-digital" name="link" placeholder="Link unduh">
      <input class="khusus-digital" name="akses" type="number" placeholder="Masa akses (hari)">
      <input class="khusus-digital" name="lisensi" placeholder="Lisensi">
    </div>
    <button>Tambah buku</button>
  </form>
</section>

<section class="panel">
  <h2>Riwayat Transaksi</h2>
  <?php if (empty($_SESSION['riwayat'])): ?>
    <p class="redup">Belum ada transaksi.</p>
  <?php else: ?>
    <ul class="riwayat"><?php foreach (array_slice($_SESSION['riwayat'], 0, 10) as $r): ?><li><?= h($r) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <form method="post" onsubmit="return confirm('Reset semua data?')">
    <button class="merah" name="aksi" value="reset">Reset data</button>
  </form>
</section>

</div>

<script>
  // Tampilkan isian khusus buku digital hanya saat jenis "Buku Digital" dipilih
  const jenis = document.getElementById('jenis');
  const grid = document.getElementById('formTambah');
  jenis.addEventListener('change', () => grid.classList.toggle('tampil-digital', jenis.value === 'digital'));
</script>
</body>
</html>