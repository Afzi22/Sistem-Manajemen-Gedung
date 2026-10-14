<?php
declare(strict_types=1);

require_once __DIR__ . '/../partials/helpers.php';
require_once __DIR__ . '/../config/db.php';

/* ==========================================================================
   LOGIKA BACKEND (boleh diganti/dipindah oleh Backend Developer)
   Menambah stok dan mencatat transaksi IN dalam SATU transaksi basis data,
   sehingga keduanya berhasil bersama atau gagal bersama.
   ========================================================================== */
function catatBarangMasuk(PDO $pdo, string $sku, int $qty, int $supplierId): void
{
    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare('UPDATE Items SET Quantity = Quantity + ? WHERE SKU = ?');
        $upd->execute([$qty, $sku]);
        if ($upd->rowCount() === 0) {
            throw new RuntimeException('Barang tidak ditemukan.');
        }

        $ins = $pdo->prepare(
            "INSERT INTO StockTransactions (SKU, Type, Quantity, SupplierID) VALUES (?, 'IN', ?, ?)"
        );
        $ins->execute([$sku, $qty, $supplierId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Data untuk dropdown ---------- */
$items     = $pdo->query('SELECT SKU, ItemName, Quantity, Unit FROM Items ORDER BY ItemName')->fetchAll();
$suppliers = $pdo->query('SELECT SupplierID, SupplierName FROM Suppliers ORDER BY SupplierName')->fetchAll();

/* ---------- Proses form ---------- */
$errors = [];
$old    = ['sku' => '', 'supplier_id' => '', 'quantity' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['sku']         = trim((string) ($_POST['sku'] ?? ''));
    $old['supplier_id'] = trim((string) ($_POST['supplier_id'] ?? ''));
    $old['quantity']    = trim((string) ($_POST['quantity'] ?? ''));

    if (!csrf_valid()) {
        $errors['form'] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    if (!in_array($old['sku'], array_column($items, 'SKU'), true)) {
        $errors['sku'] = 'Pilih barang yang valid.';
    }

    $supplierIds = array_map('strval', array_column($suppliers, 'SupplierID'));
    if (!in_array($old['supplier_id'], $supplierIds, true)) {
        $errors['supplier_id'] = 'Pilih supplier.';
    }

    $qty = filter_var($old['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($qty === false) {
        $errors['quantity'] = 'Jumlah harus bilangan bulat minimal 1.';
    }

    if (!$errors) {
        try {
            catatBarangMasuk($pdo, $old['sku'], $qty, (int) $old['supplier_id']);
            flash_set('success', 'Barang masuk berhasil dicatat dan stok sudah bertambah.');
            header('Location: /transactions/in.php');
            exit;
        } catch (RuntimeException $e) {
            $errors['form'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Barang masuk gagal: ' . $e->getMessage());
            $errors['form'] = 'Data gagal disimpan. Silakan coba lagi.';
        }
    }
}

/* ---------- Riwayat barang masuk terbaru ---------- */
$recent = $pdo->query(
    "SELECT t.TransactionID, t.SKU, i.ItemName, i.Unit, t.Quantity, t.TransactionDate, s.SupplierName
       FROM StockTransactions t
       JOIN Items i ON i.SKU = t.SKU
  LEFT JOIN Suppliers s ON s.SupplierID = t.SupplierID
      WHERE t.Type = 'IN'
   ORDER BY t.TransactionDate DESC, t.TransactionID DESC
      LIMIT 8"
)->fetchAll();

$pageTitle    = 'Barang Masuk';
$pageSubtitle = 'Catat penerimaan barang dari supplier. Stok akan bertambah otomatis.';
$activeNav    = 'in';
require __DIR__ . '/../partials/header.php';
?>

<div class="tx-layout">

  <!-- ===== Form ===== -->
  <section class="card" aria-labelledby="form-title">
    <div class="card__header">
      <h2 class="card__title" id="form-title">Form barang masuk</h2>
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
          <p>Tambahkan barang di Master Barang sebelum mencatat barang masuk.</p>
          <a class="btn btn--primary" href="/items/create.php">Tambah barang</a>
        </div>
      <?php elseif (!$suppliers): ?>
        <div class="empty">
          <p class="empty__title">Belum ada supplier</p>
          <p>Barang masuk membutuhkan data supplier. Tambahkan supplier terlebih dahulu.</p>
          <a class="btn btn--primary" href="/suppliers/list.php">Lihat supplier</a>
        </div>
      <?php else: ?>

        <form class="form" id="tx-form" method="post" action="/transactions/in.php">
          <?= csrf_field() ?>

          <div class="field">
            <label class="label" for="sku">Barang <span class="label__req" aria-hidden="true">*</span></label>
            <select class="select<?= isset($errors['sku']) ? ' is-invalid' : '' ?>" id="sku" name="sku" required
                    <?= isset($errors['sku']) ? 'aria-invalid="true" aria-describedby="sku-error"' : '' ?>>
              <option value="" disabled<?= $old['sku'] === '' ? ' selected' : '' ?>>Pilih barang…</option>
              <?php foreach ($items as $item): ?>
                <option value="<?= e($item['SKU']) ?>"
                        data-stock="<?= (int) $item['Quantity'] ?>"
                        data-unit="<?= e($item['Unit']) ?>"
                        <?= $old['sku'] === $item['SKU'] ? 'selected' : '' ?>>
                  <?= e($item['SKU']) ?> — <?= e($item['ItemName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['sku'])): ?>
              <p class="field__error" id="sku-error"><?= e($errors['sku']) ?></p>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="supplier_id">Supplier <span class="label__req" aria-hidden="true">*</span></label>
            <select class="select<?= isset($errors['supplier_id']) ? ' is-invalid' : '' ?>" id="supplier_id" name="supplier_id" required
                    <?= isset($errors['supplier_id']) ? 'aria-invalid="true" aria-describedby="supplier-error"' : '' ?>>
              <option value="" disabled<?= $old['supplier_id'] === '' ? ' selected' : '' ?>>Pilih supplier…</option>
              <?php foreach ($suppliers as $supplier): ?>
                <option value="<?= (int) $supplier['SupplierID'] ?>"
                        <?= $old['supplier_id'] === (string) $supplier['SupplierID'] ? 'selected' : '' ?>>
                  <?= e($supplier['SupplierName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['supplier_id'])): ?>
              <p class="field__error" id="supplier-error"><?= e($errors['supplier_id']) ?></p>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="quantity">Jumlah masuk <span class="label__req" aria-hidden="true">*</span></label>
            <input class="input<?= isset($errors['quantity']) ? ' is-invalid' : '' ?>"
                   type="number" id="quantity" name="quantity" min="1" step="1" inputmode="numeric"
                   placeholder="Contoh: 10" value="<?= e($old['quantity']) ?>" required
                   <?= isset($errors['quantity']) ? 'aria-invalid="true" aria-describedby="quantity-error"' : 'aria-describedby="quantity-hint"' ?>>
            <?php if (isset($errors['quantity'])): ?>
              <p class="field__error" id="quantity-error"><?= e($errors['quantity']) ?></p>
            <?php else: ?>
              <p class="field__hint" id="quantity-hint">Bilangan bulat, minimal 1.</p>
            <?php endif; ?>
          </div>

          <div class="stock-preview" id="stock-preview" aria-live="polite" hidden>
            <div class="stock-preview__col">
              <span class="stock-preview__label">Stok saat ini</span>
              <span class="stock-preview__value" id="sp-current">–</span>
            </div>
            <span class="stock-preview__arrow"><?= icon('arrow-right') ?></span>
            <div class="stock-preview__col">
              <span class="stock-preview__label">Stok setelah masuk</span>
              <span class="stock-preview__value stock-preview__after" id="sp-after">–</span>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Simpan barang masuk</button>
            <a class="btn btn--ghost" href="/index.php">Batal</a>
          </div>
        </form>

      <?php endif; ?>
    </div>
  </section>

  <!-- ===== Riwayat terbaru ===== -->
  <section class="card" aria-labelledby="recent-title">
    <div class="card__header">
      <h2 class="card__title" id="recent-title">Barang masuk terbaru</h2>
    </div>

    <?php if (!$recent): ?>
      <div class="empty">
        <p class="empty__title">Belum ada transaksi masuk</p>
        <p>Transaksi yang kamu catat akan muncul di sini.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th scope="col">Waktu</th>
              <th scope="col">Barang</th>
              <th scope="col">Supplier</th>
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
                <td><?= e($row['SupplierName'] ?? '-') ?></td>
                <td class="num">
                  <span class="qty--in">+<?= e(format_qty((int) $row['Quantity'])) ?></span>
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
  // Pratinjau stok: stok saat ini -> stok setelah barang masuk
  (function () {
    var sku     = document.getElementById('sku');
    var qty     = document.getElementById('quantity');
    var preview = document.getElementById('stock-preview');
    var current = document.getElementById('sp-current');
    var after   = document.getElementById('sp-after');
    if (!sku || !qty || !preview) return;

    var fmt = new Intl.NumberFormat('id-ID');

    function update() {
      var opt = sku.options[sku.selectedIndex];
      if (!opt || !opt.dataset.stock) { preview.hidden = true; return; }

      var stock = parseInt(opt.dataset.stock, 10);
      var unit  = opt.dataset.unit || '';
      var add   = parseInt(qty.value, 10);
      if (isNaN(add) || add < 0) add = 0;

      current.textContent = fmt.format(stock) + ' ' + unit;
      after.textContent   = fmt.format(stock + add) + ' ' + unit;
      preview.hidden = false;
    }

    sku.addEventListener('change', update);
    qty.addEventListener('input', update);
    update();
  })();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
