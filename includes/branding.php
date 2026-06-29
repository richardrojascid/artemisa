<?php
declare(strict_types=1);

const BRAND_LOGO_CIRCULAR = 'assets/images/logo-artemisa-circular.png';
const BRAND_LOGO_BANNER = 'assets/images/logo-artemisa-banner.png';

function brand_path(string $asset, string $base = ''): string
{
    $prefix = $base !== '' ? rtrim($base, '/') . '/' : '';
    return $prefix . $asset;
}
