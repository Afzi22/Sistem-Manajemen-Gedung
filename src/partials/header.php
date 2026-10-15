<?php
declare(strict_types=1);

/**
 * Layout bersama (bagian atas).
 * Sebelum memuat berkas ini, halaman mengisi:
 *   $pageTitle    (wajib)  judul halaman, tampil sebagai <h1>
 *   $pageSubtitle (opsional) keterangan singkat di bawah judul
 *   $activeNav    (wajib)  dashboard | items | suppliers | in | out
 */
require_once __DIR__ . '/helpers.php';

$pageTitle    = $pageTitle ?? 'Gudang Pustaka';
$pageSubtitle = $pageSubtitle ?? '';
$activeNav    = $activeNav ?? '';

$navGroups = [
    'Ringkasan' => [
        'dashboard' => ['/index.php', 'Dashboard', 'dashboard'],
    ],
    'Data' => [
        'items'     => ['/items/list.php', 'Master Barang', 'box'],
        'suppliers' => ['/suppliers/list.php', 'Supplier', 'truck'],
    ],
    'Transaksi' => [
        'in'  => ['/transactions/in.php', 'Barang Masuk', 'arrow-down'],
        'out' => ['/transactions/out.php', 'Barang Keluar', 'arrow-up'],
    ],
];

$flashIcons = ['success' => 'check', 'error' => 'alert', 'warning' => 'alert', 'info' => 'info'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · Gudang Pustaka</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/base.css">
  <link rel="stylesheet" href="/assets/css/components.css">
  <link rel="stylesheet" href="/assets/css/pages.css">
</head>
<body>
  <a class="skip-link" href="#main">Lewati ke konten utama</a>

  <div class="app">
    <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
      <a class="sidebar__brand" href="/index.php">
        <span class="sidebar__mark"><?= icon('box', 18) ?></span>
        <span>Gudang Pustaka</span>
      </a>

      <nav class="nav">
        <?php foreach ($navGroups as $groupLabel => $links): ?>
          <p class="nav__label"><?= e($groupLabel) ?></p>
          <ul class="nav__list">
            <?php foreach ($links as $key => [$href, $label, $iconName]): ?>
              <li>
                <a class="nav__link<?= $key === $activeNav ? ' is-active' : '' ?>"
                   href="<?= e($href) ?>"
                   <?= $key === $activeNav ? 'aria-current="page"' : '' ?>>
                  <?= icon($iconName) ?>
                  <span><?= e($label) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </nav>
    </aside>

    <div class="sidebar-overlay" id="sidebar-overlay" hidden></div>

    <div class="app__main">
      <header class="topbar">
        <button type="button" class="btn btn--ghost btn--icon" id="menu-toggle"
                aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu">
          <?= icon('menu') ?>
        </button>
        <span class="topbar__title">Gudang Pustaka</span>
      </header>

      <main class="content" id="main">
        <div class="page-header">
          <h1 class="page-header__title"><?= e($pageTitle) ?></h1>
          <?php if ($pageSubtitle !== ''): ?>
            <p class="page-header__subtitle"><?= e($pageSubtitle) ?></p>
          <?php endif; ?>
        </div>

        <?php foreach (flash_pull() as $flash): ?>
          <div class="alert alert--<?= e($flash['type']) ?>" role="status">
            <?= icon($flashIcons[$flash['type']] ?? 'info') ?>
            <span><?= e($flash['message']) ?></span>
          </div>
        <?php endforeach; ?>
