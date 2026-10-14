<?php
declare(strict_types=1);

require_once __DIR__ . '/../partials/helpers.php';
require_once __DIR__ . '/../config/db.php';

/* ==========================================================================
   LOGIKA BACKEND (boleh diganti/dipindah oleh Backend Developer)
   Mengurangi stok hanya bila stok cukup, lalu mencatat transaksi OUT
   dalam SATU transaksi basis data. Pengecekan "Quantity >= ?" dilakukan
   di dalam query agar dua pengeluaran bersamaan tidak membuat stok negatif.
   ========================================================================== */
function catatBarangKeluar(PDO $pdo, string $sku, int $qty): void
{
    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare('UPDATE Items SET Quantity = Quantity - ? WHERE SKU = ? AND Quantity >= ?');
        $upd->execute([$qty, $sku, $qty]);
        if ($upd->rowCount() === 0) {
            throw new RuntimeException('Stok tidak cukup atau barang tidak ditemukan.');
        }

        $ins = $pdo->prepare(
            "INSERT INTO StockTransactions (SKU, Type, Quantity) VALUES (?, 'OUT', ?)"
        );
        $ins->execute([$sku, $qty]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Data untuk dropdown ---------- */
$items = $pdo->query('SELECT SKU, ItemName, Quantity, Unit FROM Items ORDER BY ItemName')->fetchAll();

/* ---------- Proses form ---------- */
$errors = [];
$old    = ['sku' => '', 'quantity' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['sku']      = trim((string) ($_POST['sku'] ?? ''));
    $old['quantity'] = trim((string) ($_POST['quantity'] ?? ''));

    if (!csrf_valid()) {
        $errors['form'] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    if (!in_array($old['sku'], array_column($items, 'SKU'), true)) {
        $errors['sku'] = 'Pilih barang yang valid.';
    }

    $qty = filter_var($old['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($qty === false) {
        $errors['quantity'] = 'Jumlah harus bilangan bulat minimal 1.';
    }

    if (!$errors) {
        try {
            catatBarangKeluar($pdo, $old['sku'], $qty);
            flash_set('success', 'Barang keluar berhasil dicatat dan stok sudah berkurang.');
            header('Location: /transactions/out.php');
            exit;
        } catch (RuntimeException $e) {
            // Pesan stok tidak cukup ditampilkan di dekat kolom jumlah.
            $errors['quantity'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Barang keluar gagal: ' . $e->getMessage());
            $errors['form'] = 'Data gagal disimpan. Silakan coba lagi.';
        }
    }
}

/* ---------- Riwayat barang keluar terbaru ---------- */
$recent = $pdo->query(
    "SELECT t.TransactionID, t.SKU, i.ItemName, i.Unit, t.Quantity, t.TransactionDate
       FROM StockTransactions t
       JOIN Items i ON i.SKU = t.SKU
      WHERE t.Type = 'OUT'
   ORDER BY t.TransactionDate DESC, t.TransactionID DESC
      LIMIT 8"
)->fetchAll();

$pageTitle    = 'Barang Keluar';
$pageSubtitle = 'Catat pengeluaran barang dari gudang. Stok akan berkurang otomatis.';
$activeNav    = 'out';
require __DIR__ . '/../partials/header.php';
?>

<div class="tx-layout">

  <!-- ===== Form ===== -->
  <section class="card" aria-labelledby="form-title">
    <div class="card__header">
      <h2 class="card__title" id="form-title">Form barang keluar</h2>
    </div>
    <div class="card__body">

      <?php if (isset($errors['form'])): ?>
        <div class="alert alert--error" role="alert">
          <?= icon('alert') ?>
          <span><?= e($errors['form']) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!$items): ?>
        <div class="empty">
          <p class="empty__title">Belum ada barang</p>
          <p>Tambahkan barang di Master Barang sebelum mencatat barang keluar.</p>
          <a class="btn btn--primary" href="/items/create.php">Tambah barang</a>
        </div>
      <?php else: ?>

        <form class="form" id="tx-form" method="post" action="/transactions/out.php">
          <?= csrf_field() ?>

          <div class="field">
            <label class="label" for="sku">Barang <span class="label__req" aria-hidden="true">*</span></label>
            <select class="select<?= isset($errors['sku']) ? ' is-invalid' : '' ?>" id="sku" name="sku" required
                    <?= isset($errors['sku']) ? 'aria-invalid="true" aria-describedby="sku-error"' : '' ?>>
              <option value="" disabled<?= $old['sku'] === '' ? ' selected' : '' ?>>Pilih barang…</option>
              <?php foreach ($items as $item): ?>
                <?php $habis = (int) $item['Quantity'] === 0; ?>
                <option value="<?= e($item['SKU']) ?>"
                        data-stock="<?= (int) $item['Quantity'] ?>"
                        data-unit="<?= e($item['Unit']) ?>"
                        <?= $habis ? 'disabled' : '' ?>
                        <?= $old['sku'] === $item['SKU'] ? 'selected' : '' ?>>
                  <?= e($item['SKU']) ?> — <?= e($item['ItemName']) ?><?= $habis ? ' (stok habis)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['sku'])): ?>
              <p class="field__error" id="sku-error"><?= e($errors['sku']) ?></p>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="quantity">Jumlah keluar <span class="label__req" aria-hidden="true">*</span></label>
            <input class="input<?= isset($errors['quantity']) ? ' is-invalid' : '' ?>"
                   type="number" id="quantity" name="quantity" min="1" step="1" inputmode="numeric"
                   placeholder="Contoh: 5" value="<?= e($old['quantity']) ?>" required
                   <?= isset($errors['quantity']) ? 'aria-invalid="true" aria-describedby="quantity-error"' : 'aria-describedby="quantity-hint"' ?>>
            <?php if (isset($errors['quantity'])): ?>
              <p class="field__error" id="quantity-error"><?= e($errors['quantity']) ?></p>
            <?php else: ?>
              <p class="field__hint" id="quantity-hint">Tidak boleh melebihi stok yang tersedia.</p>
            <?php endif; ?>
          </div>

          <div class="stock-preview" id="stock-preview" aria-live="polite" hidden>
            <div class="stock-preview__col">
              <span class="stock-preview__label">Stok saat ini</span>
              <span class="stock-preview__value" id="sp-current">–</span>
            </div>
            <span class="stock-preview__arrow"><?= icon('arrow-right') ?></span>
            <div class="stock-preview__col">
              <span class="stock-preview__label">Stok setelah keluar</span>
              <span class="stock-preview__value stock-preview__after" id="sp-after">–</span>
            </div>
            <span class="stock-preview__note" id="sp-note" hidden>Melebihi stok</span>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--accent">Simpan barang keluar</button>
            <a class="btn btn--ghost" href="/index.php">Batal</a>
          </div>
        </form>

      <?php endif; ?>
    </div>
  </section>

  <!-- ===== Riwayat terbaru ===== -->
  <section class="card" aria-labelledby="recent-title">
    <div class="card__header">
      <h2 class="card__title" id="recent-title">Barang keluar terbaru</h2>
    </div>

    <?php if (!$recent): ?>
      <div class="empty">
        <p class="empty__title">Belum ada transaksi keluar</p>
        <p>Transaksi yang kamu catat akan muncul di sini.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th scope="col">Waktu</th>
              <th scope="col">Barang</th>
              <th scope="col" class="num">Jumlah</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $row): ?>
              <tr>
                <td><?= e(format_datetime((string) $row['TransactionDate'])) ?></td>
                <td>
                  <?= e($row['ItemName']) ?>
                  <span class="table__sub"><?= e($row['SKU']) ?></span>
                </td>
                <td class="num">
                  <span class="qty--out">−<?= e(format_qty((int) $row['Quantity'])) ?></span>
                  <span class="table__sub"><?= e($row['Unit']) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

</div>

<script>
  // Pratinjau stok + peringatan bila jumlah melebihi stok tersedia
  (function () {
    var sku     = document.getElementById('sku');
    var qty     = document.getElementById('quantity');
    var preview = document.getElementById('stock-preview');
    var current = document.getElementById('sp-current');
    var after   = document.getElementById('sp-after');
    var note    = document.getElementById('sp-note');
    if (!sku || !qty || !preview) return;

    var fmt = new Intl.NumberFormat('id-ID');

    function update() {
      var opt = sku.options[sku.selectedIndex];
      if (!opt || !opt.dataset.stock) {
        preview.hidden = true;
        qty.removeAttribute('max');
        qty.setCustomValidity('');
        return;
      }

      var stock = parseInt(opt.dataset.stock, 10);
      var unit  = opt.dataset.unit || '';
      var out   = parseInt(qty.value, 10);
      if (isNaN(out) || out < 0) out = 0;

      var over = out > stock;
      qty.max = stock;
      qty.setCustomValidity(over ? 'Jumlah melebihi stok tersedia (' + fmt.format(stock) + ' ' + unit + ').' : '');

      current.textContent = fmt.format(stock) + ' ' + unit;
      after.textContent   = over ? '–' : fmt.format(stock - out) + ' ' + unit;
      note.hidden = !over;
      preview.classList.toggle('is-over', over);
      preview.hidden = false;
    }

    sku.addEventListener('change', update);
    qty.addEventListener('input', update);
    update();
  })();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
