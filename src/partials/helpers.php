<?php
declare(strict_types=1);

/**
 * Fungsi bantu yang dipakai semua halaman.
 * Muat di baris paling atas halaman (sebelum ada output apa pun)
 * supaya session dan redirect berfungsi.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Escape teks sebelum dicetak ke HTML (mencegah XSS). */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Format bilangan bulat gaya Indonesia: 1.250 */
function format_qty(int $n): string
{
    return number_format($n, 0, ',', '.');
}

/** Format tanggal-waktu dari basis data: 14/10/2026 09:30 */
function format_datetime(string $value): string
{
    $ts = strtotime($value);
    return $ts === false ? e($value) : date('d/m/Y H:i', $ts);
}

/* ---------- Flash message (tampil sekali setelah redirect) ---------- */

/** $type: success | error | warning | info */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_pull(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------- CSRF: memastikan form dikirim dari halaman kita sendiri ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

/* ---------- Ikon SVG inline (tanpa pustaka eksternal) ---------- */

function icon(string $name, int $size = 20): string
{
    $paths = [
        'dashboard'   => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'box'         => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'arrow-down'  => '<path d="M12 4v12"/><path d="m6 11 6 6 6-6"/><path d="M5 20h14"/>',
        'arrow-up'    => '<path d="M12 20V8"/><path d="m6 13 6-6 6 6"/><path d="M5 4h14"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'truck'       => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'menu'        => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'check'       => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'alert'       => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16.5h.01"/>',
        'info'        => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 7.5h.01"/>',
    ];

    $body = $paths[$name] ?? '';
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
         . 'aria-hidden="true">' . $body . '</svg>';
}
