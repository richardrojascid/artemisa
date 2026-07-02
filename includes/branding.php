<?php
declare(strict_types=1);

const BRAND_LOGO_CENTRAL = 'assets/images/artemisa-logo-central.png';
const BRAND_LOGO_CIRCULAR = 'assets/images/logo-artemisa-circular.png';
const BRAND_LOGO_BANNER = 'assets/images/logo-artemisa-banner.png';

function brand_path(string $asset, string $base = ''): string
{
    $prefix = $base !== '' ? rtrim($base, '/') . '/' : '';
    return $prefix . $asset;
}

/** Evita caché del navegador tras actualizar CSS/JS */
function asset_url(string $asset, string $base = ''): string
{
    $path = brand_path($asset, $base);
    $full = BASE_PATH . '/' . ltrim($asset, '/');
    $version = is_file($full) ? (string) filemtime($full) : APP_VERSION;
    return $path . '?v=' . rawurlencode($version);
}
