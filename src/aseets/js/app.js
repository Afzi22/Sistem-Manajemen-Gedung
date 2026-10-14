/* ==========================================================================
   app.js - kerangka layout + data contoh (frontend saja, tanpa backend)
   Dipakai oleh: supplier/list.html, transactions/in.html, transactions/out.html

   CATATAN UNTUK BACKEND DEVELOPER
   Semua pembacaan/penyimpanan data lewat objek EG.store di bawah ini.
   Saat backend siap, ganti isi fungsi store (getItems, getSuppliers,
   addSupplier, recordIn, recordOut, ...) dengan fetch() ke API/PHP.
   Halaman tidak perlu diubah.
   ========================================================================== */

(function () {
  'use strict';

  var STORAGE_KEY = 'egedung:v1';

  /* ---------- Data contoh ---------- */
  function seed() {
    return {
      items: [
        { sku: 'LMP-LED-18', name: 'Lampu LED 18 Watt',        unit: 'buah',   qty: 120 },
        { sku: 'FLT-AC-01',  name: 'Filter AC Split',          unit: 'buah',   qty: 40 },
        { sku: 'PVC-34',     name: 'Pipa PVC 3/4 inci',        unit: 'batang', qty: 60 },
        { sku: 'KBL-NYM-215', name: 'Kabel NYM 2x1,5 mm',      unit: 'roll',   qty: 8 },
        { sku: 'CAT-TMB-20', name: 'Cat tembok putih 20 kg',   unit: 'pail',   qty: 12 },
        { sku: 'CLN-LNT-5',  name: 'Pembersih lantai 5 liter', unit: 'jerigen', qty: 25 },
        { sku: 'APAR-3',     name: 'Tabung APAR 3 kg',         unit: 'unit',   qty: 6 }
      ],
      suppliers: [
        { id: 1, name: 'PT Terang Karya Elektrik', category: 'Kelistrikan',       phone: '021 5550 1201' },
        { id: 2, name: 'CV Tirta Mandiri',         category: 'Plumbing',          phone: '0812 3456 7801' },
        { id: 3, name: 'PT Sejuk Prima Teknik',    category: 'Pendingin (AC)',    phone: '022 4200 3344' },
        { id: 4, name: 'PT Bersih Sentosa',        category: 'Kebersihan',        phone: '0813 9876 5432' },
        { id: 5, name: 'PT Proteksi Nusantara',    category: 'Keamanan & K3',     phone: '021 5550 7788' },
        { id: 6, name: 'UD Warna Gedung',          category: 'Material bangunan', phone: '' }
      ],
      transactions: [
        { id: 1, type: 'IN',  sku: 'LMP-LED-18', qty: 60, supplierId: 1, ref: 'SJ-2610-014', note: '',                          date: '2026-10-02T09:15:00' },
        { id: 2, type: 'IN',  sku: 'FLT-AC-01',  qty: 20, supplierId: 3, ref: 'SJ-2610-021', note: '',                          date: '2026-10-05T10:40:00' },
        { id: 3, type: 'OUT', sku: 'LMP-LED-18', qty: 12, location: 'Lantai 3 - Koridor',    note: 'Ganti lampu mati',         date: '2026-10-06T13:20:00' },
        { id: 4, type: 'IN',  sku: 'CLN-LNT-5',  qty: 10, supplierId: 4, ref: 'SJ-2610-033', note: '',                          date: '2026-10-08T08:30:00' },
        { id: 5, type: 'OUT', sku: 'FLT-AC-01',  qty: 8,  location: 'Lantai 2 - Ruang Rapat', note: 'Servis AC berkala',       date: '2026-10-09T14:05:00' },
        { id: 6, type: 'IN',  sku: 'APAR-3',     qty: 4,  supplierId: 5, ref: 'SJ-2610-040', note: 'Penggantian APAR kedaluwarsa', date: '2026-10-12T11:00:00' },
        { id: 7, type: 'OUT', sku: 'PVC-34',     qty: 6,  location: 'Basement',               note: 'Perbaikan pipa bocor',    date: '2026-10-13T15:45:00' }
      ]
    };
  }

  var LOCATIONS = [
    'Lobi & Resepsionis', 'Lantai 1 - Area Publik', 'Lantai 2 - Ruang Rapat',
    'Lantai 3 - Koridor', 'Lantai 3 - Ruang Server', 'Lantai 4 - Perkantoran',
    'Basement', 'Area Parkir', 'Atap (Rooftop)', 'Ruang Genset & Panel'
  ];

  var CATEGORIES = [
    'Kelistrikan', 'Plumbing', 'Pendingin (AC)', 'Kebersihan',
    'Keamanan & K3', 'Material bangunan', 'Lainnya'
  ];

  /* ---------- Store (localStorage; aman bila tidak tersedia) ---------- */
  var memory = null;

  function load() {
    if (memory) return memory;
    try {
      var raw = window.localStorage.getItem(STORAGE_KEY);
      if (raw) { memory = JSON.parse(raw); return memory; }
    } catch (err) { /* abaikan, pakai data contoh */ }
    memory = seed();
    return memory;
  }

  function save() {
    try { window.localStorage.setItem(STORAGE_KEY, JSON.stringify(memory)); }
    catch (err) { /* data tetap ada selama halaman terbuka */ }
  }

  function nextId(list) {
    return list.reduce(function (m, r) { return Math.max(m, r.id); }, 0) + 1;
  }

  var store = {
    getItems:        function () { return load().items.slice().sort(byName); },
    getSuppliers:    function () { return load().suppliers.slice().sort(byName); },
    getTransactions: function (type) {
      return load().transactions
        .filter(function (t) { return !type || t.type === type; })
        .sort(function (a, b) { return b.date.localeCompare(a.date) || b.id - a.id; });
    },
    findItem: function (sku) {
      return load().items.filter(function (i) { return i.sku === sku; })[0] || null;
    },
    findSupplier: function (id) {
      return load().suppliers.filter(function (s) { return s.id === Number(id); })[0] || null;
    },

    addSupplier: function (data) {
      var db = load();
      var row = { id: nextId(db.suppliers), name: data.name, category: data.category, phone: data.phone };
      db.suppliers.push(row);
      save();
      return row;
    },

    /* Menambah stok + mencatat transaksi IN sekaligus */
    recordIn: function (data) {
      var db = load();
      var item = store.findItem(data.sku);
      if (!item) throw new Error('Barang tidak ditemukan.');
      item.qty += data.qty;
      db.transactions.push({
        id: nextId(db.transactions), type: 'IN', sku: data.sku, qty: data.qty,
        supplierId: Number(data.supplierId), ref: data.ref, note: data.note, date: nowIso()
      });
      save();
    },

    /* Mengurangi stok hanya bila cukup + mencatat transaksi OUT */
    recordOut: function (data) {
      var db = load();
      var item = store.findItem(data.sku);
      if (!item) throw new Error('Barang tidak ditemukan.');
      if (item.qty < data.qty) throw new Error('Stok tidak cukup. Tersedia ' + item.qty + ' ' + item.unit + '.');
      item.qty -= data.qty;
      db.transactions.push({
        id: nextId(db.transactions), type: 'OUT', sku: data.sku, qty: data.qty,
        location: data.location, note: data.note, date: nowIso()
      });
      save();
    },

    reset: function () { memory = seed(); save(); }
  };

  function byName(a, b) { return a.name.localeCompare(b.name, 'id'); }

  function nowIso() {
    var d = new Date();
    function p(n) { return (n < 10 ? '0' : '') + n; }
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) +
           'T' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  }

  /* ---------- Helper tampilan ---------- */
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  var nf = new Intl.NumberFormat('id-ID');
  function formatQty(n) { return nf.format(n); }

  function formatDateTime(iso) {
    var d = new Date(iso);
    if (isNaN(d.getTime())) return esc(iso);
    function p(n) { return (n < 10 ? '0' : '') + n; }
    return p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear() +
           ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
  }

  var ICONS = {
    'dashboard':   '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
    'box':         '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
    'arrow-down':  '<path d="M12 4v12"/><path d="m6 11 6 6 6-6"/><path d="M5 20h14"/>',
    'arrow-up':    '<path d="M12 20V8"/><path d="m6 13 6-6 6 6"/><path d="M5 4h14"/>',
    'arrow-right': '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
    'truck':       '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
    'menu':        '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
    'check':       '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    'alert':       '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16.5h.01"/>',
    'info':        '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 7.5h.01"/>',
    'plus':        '<path d="M12 5v14"/><path d="M5 12h14"/>',
    'search':      '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>'
  };

  function icon(name, size) {
    size = size || 20;
    return '<svg class="icon" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" ' +
           'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' +
           'aria-hidden="true">' + (ICONS[name] || '') + '</svg>';
  }

  /* Tampilkan/hapus pesan galat di bawah sebuah kolom form */
  function fieldError(input, message) {
    var field = input.closest('.field');
    var old = field.querySelector('.field__error');
    if (old) old.remove();
    var hint = field.querySelector('.field__hint');

    if (!message) {
      input.classList.remove('is-invalid');
      input.removeAttribute('aria-invalid');
      if (hint) { hint.hidden = false; input.setAttribute('aria-describedby', hint.id); }
      return;
    }
    var p = document.createElement('p');
    p.className = 'field__error';
    p.id = input.id + '-error';
    p.textContent = message;
    field.appendChild(p);
    input.classList.add('is-invalid');
    input.setAttribute('aria-invalid', 'true');
    input.setAttribute('aria-describedby', p.id);
    if (hint) hint.hidden = true;
  }

  /* Pesan sukses/galat di bagian atas halaman */
  function flash(type, message) {
    var box = document.getElementById('flash');
    if (!box) return;
    var iconName = { success: 'check', error: 'alert', warning: 'alert', info: 'info' }[type] || 'info';
    box.innerHTML = '<div class="alert alert--' + esc(type) + '" role="' + (type === 'error' ? 'alert' : 'status') + '">' +
                    icon(iconName) + '<span>' + esc(message) + '</span></div>';
  }

  /* ---------- Kerangka layout (sidebar, topbar, judul halaman) ---------- */
  var NAV = [
    { label: 'Ringkasan', links: [
      { key: 'dashboard', href: '/index.html',               text: 'Dashboard',     icon: 'dashboard' } ] },
    { label: 'Data', links: [
      { key: 'items',     href: '/items/list.html',          text: 'Master Barang', icon: 'box' },
      { key: 'suppliers', href: '/supplier/list.html',       text: 'Supplier',      icon: 'truck' } ] },
    { label: 'Transaksi', links: [
      { key: 'in',        href: '/transactions/in.html',     text: 'Barang Masuk',  icon: 'arrow-down' },
      { key: 'out',       href: '/transactions/out.html',    text: 'Barang Keluar', icon: 'arrow-up' } ] }
  ];

  function buildShell() {
    var body = document.body;
    var content = document.getElementById('page-content');
    if (!content) return;

    var root = body.getAttribute('data-root') || '..';
    var active = body.getAttribute('data-active') || '';
    var title = body.getAttribute('data-title') || 'E-Gedung';
    var subtitle = body.getAttribute('data-subtitle') || '';

    var nav = NAV.map(function (g) {
      return '<p class="nav__label">' + esc(g.label) + '</p><ul class="nav__list">' +
        g.links.map(function (l) {
          var on = l.key === active;
          return '<li><a class="nav__link' + (on ? ' is-active' : '') + '" href="' + root + l.href + '"' +
                 (on ? ' aria-current="page"' : '') + '>' + icon(l.icon) + '<span>' + esc(l.text) + '</span></a></li>';
        }).join('') + '</ul>';
    }).join('');

    var shell = document.createElement('div');
    shell.className = 'app';
    shell.innerHTML =
      '<aside class="sidebar" id="sidebar" aria-label="Navigasi utama">' +
        '<a class="sidebar__brand" href="' + root + '/index.html"><span class="sidebar__mark">' + icon('box', 18) + '</span><span>E-Gedung</span></a>' +
        '<nav class="nav">' + nav + '</nav>' +
        '<div class="sidebar__foot">' +
          '<button type="button" class="sidebar__reset" id="reset-data">Reset data contoh</button>' +
          '<p class="sidebar__note">Mode tampilan: data tersimpan di browser ini saja.</p>' +
        '</div>' +
      '</aside>' +
      '<div class="sidebar-overlay" id="sidebar-overlay" hidden></div>' +
      '<div class="app__main">' +
        '<header class="topbar">' +
          '<button type="button" class="btn btn--ghost btn--icon" id="menu-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu">' + icon('menu') + '</button>' +
          '<span class="topbar__title">E-Gedung</span>' +
        '</header>' +
        '<main class="content" id="main">' +
          '<div class="page-header"><h1 class="page-header__title">' + esc(title) + '</h1>' +
            (subtitle ? '<p class="page-header__subtitle">' + esc(subtitle) + '</p>' : '') + '</div>' +
          '<div id="flash"></div>' +
        '</main>' +
      '</div>';

    var skip = document.createElement('a');
    skip.className = 'skip-link';
    skip.href = '#main';
    skip.textContent = 'Lewati ke konten utama';

    body.insertBefore(shell, body.firstChild);
    body.insertBefore(skip, body.firstChild);
    shell.querySelector('#main').appendChild(content);

    document.title = title + ' · E-Gedung';

    /* Menu samping untuk layar kecil */
    var toggle = shell.querySelector('#menu-toggle');
    var sidebar = shell.querySelector('#sidebar');
    var overlay = shell.querySelector('#sidebar-overlay');
    function setOpen(open) {
      sidebar.classList.toggle('is-open', open);
      overlay.hidden = !open;
      toggle.setAttribute('aria-expanded', String(open));
    }
    toggle.addEventListener('click', function () { setOpen(!sidebar.classList.contains('is-open')); });
    overlay.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') setOpen(false); });

    shell.querySelector('#reset-data').addEventListener('click', function () {
      if (window.confirm('Kembalikan semua data ke data contoh awal?')) {
        store.reset();
        window.location.reload();
      }
    });
  }

  window.EG = {
    store: store, esc: esc, icon: icon, formatQty: formatQty, formatDateTime: formatDateTime,
    fieldError: fieldError, flash: flash, LOCATIONS: LOCATIONS, CATEGORIES: CATEGORIES,
    init: function (pageInit) {
      buildShell();
      if (typeof pageInit === 'function') pageInit();
    }
  };
})();
