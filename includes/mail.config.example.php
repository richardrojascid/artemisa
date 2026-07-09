<?php
declare(strict_types=1);

/**
 * Configuración SMTP Titan (producción).
 *
 * 1. Copia este archivo como mail.config.php en la misma carpeta includes/
 * 2. Pon la contraseña de rrc.all.services@selfie3dchile.com
 * 3. En Titan webmail activa "Enable Titan on other apps"
 *
 * No subas mail.config.php a GitHub (contiene la contraseña).
 */

define('MAIL_DRIVER', 'smtp');
define('SMTP_HOST', 'smtp.titan.email');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USER', 'rrc.all.services@selfie3dchile.com');
define('SMTP_PASS', 'TU_CONTRASEÑA_TITAN');
define('SMTP_TIMEOUT', 20);
