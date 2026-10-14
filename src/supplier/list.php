<?php
declare(strict_types=1);

require_once __DIR__ . '/../partials/helpers.php';
require_once __DIR__ . '/../config/db.php';

/* ==========================================================================
   LOGIKA BACKEND (boleh diganti/dipindah oleh Backend Developer)
   Menyimpan supplier baru. Telepon boleh kosong (disimpan sebagai NULL).
   ========================================================================== */
function tambahSupplier(PDO $pdo, string $name, ?string $phone): void
{
    $stmt = $pdo->prepare('INSERT INTO Suppliers (SupplierName, Phone) VALUES (?, ?)');
    $stmt->execute([$name, $phone]);
}

/* ---------- Proses form tambah supplier ---------- */
$errors = [];
$old    = ['name' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']  = trim((string) ($_POST['name'] ?? ''));
    $old['phone'] = trim((string) ($_POST['phone'] ?? ''));

    if (!csrf_valid()) {
        $errors['form'] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    if ($old['name'] === '') {
        $errors['name'] = 'Nama supplier wajib diisi.';
    } elseif (mb_strlen($old['name']) > 255) {
        $errors['name'] = 'Nama supplier maksimal 255 karakter.';
    }

    if ($old['phone'] !== '' && !preg_match('/^[0-9+()\-\s]{6,20}$/', $old['phone'])) {
        $errors['phone'] = 'Telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung (6–20 karakter).';
    }

    if (!$errors) {
        try {
            tambahSupplier($pdo, $old['name'], $old['phone'] === '' ? null : $old['phone']);
            flash_set('success', 'Supplier "' . $old['name'] . '" berhasil ditambahkan.');
            header('Location: /suppliers/list.php');
            exit;
        } catch (Throwable $e) {
            error_log('Tambah supplier gagal: ' . $e->getMessage());
            $errors['form'] = 'Data gagal disimpan. Silakan coba lagi.';
        }
    }
}

/* ---------- Daftar supplier + ringkasan transaksi masuknya ---------- */
$suppliers = $pdo->query(
    "SELECT s.SupplierID, s.SupplierName, s.Phone,
            COUNT(t.TransactionID) AS TotalIn,
            MAX(t.TransactionDate) AS LastIn
       FROM Suppliers s
  LEFT JOIN StockTransactions t ON t.SupplierID = s.SupplierID AND t.Type = 'IN'
   GROUP BY s.SupplierID, s.SupplierName, s.Phone
   ORDER BY s.SupplierName"
)->fetchAll();

$pageTitle    = 'Supplier';
$pageSubtitle = 'Daftar pemasok barang dan riwayat pengirimannya ke gudang.';
$activeNav    = 'suppliers';
require __DIR__ . '/../partials/header.php';
?>

<div class="supplier-layout">

  <!-- ===== Daftar supplier ===== -->
  <section class="card" aria-labelledby="list-title">
    <div class="card__header card__header--split">
      <h2 class="card__title" id="list-title">Daftar supplier</h2>
      <span class="badge badge--neutral"><?= count($suppliers) ?> supplier</span>
    </div>

    <?php if (!$suppliers): ?>
      <div class="empty">
        <p class="empty__title">Belum ada supplier</p>
        <p>Tambahkan supplier pertama lewat formulir "Tambah supplier".</p>
      </div>
    <?php else: ?>

      <div class="toolbar">
        <div class="search">
          <?= icon('search', 18) ?>
          <label class="visually-hidden" for="supplier-search">Cari supplier</label>
          <input class="input" type="search" id="supplier-search" placeholder="Cari nama atau telepon…" autocomplete="off">
        </div>
      </div>

      <div class="table-wrap">
        <table class="table" id="supplier-table">
          <thead>
            <tr>
              <th scope="col">Supplier</th>
              <th scope="col">Telepon</th>
              <th scope="col" class="num">Pengiriman</th>
              <th scope="col">Terakhir kirim</th>
              <th scope="col"><span class="visually-hidden">Aksi</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($suppliers as $s): ?>
              <?php
                $phone  = (string) ($s['Phone'] ?? '');
                $search = mb_strtolower($s['SupplierName'] . ' ' . $phone);
              ?>
              <tr data-search="<?= e($search) ?>">
                <td>
                  <?= e($s['SupplierName']) ?>
                  <span class="table__sub">ID <?= (int) $s['SupplierID'] ?></span>
                </td>
                <td class="supplier-phone">
                  <?php if ($phone !== ''): ?>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td class="num">
                  <?= e(format_qty((int) $s['TotalIn'])) ?>
                  <span class="table__sub">kali</span>
                </td>
                <td>
                  <?= $s['LastIn'] ? e(format_datetime((string) $s['LastIn'])) : '<span class="text-muted">Belum ada</span>' ?>
                </td>
                <td>
                  <a class="btn btn--ghost btn--sm"
                     href="/transactions/in.php?supplier=<?= (int) $s['SupplierID'] ?>">
                    <?= icon('arrow-down', 16) ?> Barang masuk
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr id="no-result" hidden>
              <td colspan="5" class="text-muted">Tidak ada supplier yang cocok dengan pencarian.</td>
            </tr>
          </tbody>
        </table>
      </div>

    <?php endif; ?>
  </section>

  <!-- ===== Form tambah supplier ===== -->
  <section class="card" aria-labelledby="add-title">
    <div class="card__header">
      <h2 class="card__title" id="add-title">Tambah supplier</h2>
    </div>
    <div class="card__body">

      <?php if (isset($errors['form'])): ?>
        <div class="alert alert--error" role="alert">
          <?= icon('alert') ?>
          <span><?= e($errors['form']) ?></span>
        </div>
      <?php endif; ?>

      <form class="form" method="post" action="/suppliers/list.php">
        <?= csrf_field() ?>

        <div class="field">
          <label class="label" for="name">Nama supplier <span class="label__req" aria-hidden="true">*</span></label>
          <input class="input<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
                 type="text" id="name" name="name" maxlength="255" required
                 placeholder="Contoh: PT Sumber Makmur" value="<?= e($old['name']) ?>"
                 <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
          <?php if (isset($errors['name'])): ?>
            <p class="field__error" id="name-error"><?= e($errors['name']) ?></p>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="label" for="phone">Telepon</label>
          <input class="input<?= isset($errors['phone']) ? ' is-invalid' : '' ?>"
                 type="tel" id="phone" name="phone" maxlength="20" inputmode="tel"
                 placeholder="Contoh: 0812 3456 7890" value="<?= e($old['phone']) ?>"
                 <?= isset($errors['phone']) ? 'aria-invalid="true" aria-describedby="phone-error"' : 'aria-describedby="phone-hint"' ?>>
          <?php if (isset($errors['phone'])): ?>
            <p class="field__error" id="phone-error"><?= e($errors['phone']) ?></p>
          <?php else: ?>
            <p class="field__hint" id="phone-hint">Opsional.</p>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn--primary"><?= icon('plus', 18) ?> Simpan supplier</button>
        </div>
      </form>
    </div>
  </section>

</div>

<script>
  // Pencarian supplier di sisi browser (tanpa memuat ulang halaman)
  (function () {
    var input = document.getElementById('supplier-search');
    var table = document.getElementById('supplier-table');
    var empty = document.getElementById('no-result');
    if (!input || !table || !empty) return;

    var rows = table.querySelectorAll('tbody tr[data-search]');

    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      var shown = 0;
      rows.forEach(function (row) {
        var match = q === '' || row.dataset.search.indexOf(q) !== -1;
        row.hidden = !match;
        if (match) shown++;
      });
      empty.hidden = shown !== 0;
    });
  })();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
