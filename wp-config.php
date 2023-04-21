<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'archive_kblstudio_com');

/** MySQL database username */
define('DB_USER', 'archivekblstudio');

/** MySQL database password */
define('DB_PASSWORD', 'zeK^iN?y');

/** MySQL hostname */
define('DB_HOST', 'mysql.archive.kblstudio.com');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'dvT|s069/VkupYwQu&*6zXpV?(OhGN4|aE(?eNI_gHobBu(SifVfv@_6$^qs&S+R');
define('SECURE_AUTH_KEY',  'gq0JCnc2925I6GtxGkeqeX`X^Me5vqT|#JF?gUV&ix$*bvU/A65VJYp"&_th6Yu5');
define('LOGGED_IN_KEY',    '~ylC9qy2~D:LFK^qSYgrry;ciEqzvDtTpPT~dKplQW8GM(1W8RNlFQ"j**?5H(Gp');
define('NONCE_KEY',        'P6bw$z)vkyHizkUGIvIc(MJKKNB$(:BUfPX9n0/+#2cnkMCBv_oJ:N6Iv0R!j/kj');
define('AUTH_SALT',        'Sf+;gmW:nstn0jujnGSl@;9Z3R8@;:Vdw0A#qnOCx~;FI:iCt_;:TE@kTDodP)dY');
define('SECURE_AUTH_SALT', '?)UXLowant~duEZ2bMF!Hs$`h8imo3)OdtZcjqq*t7WRl(JpBGq4Zw)fyHGcrP$X');
define('LOGGED_IN_SALT',   'Y6C*epg4IZ$S|A"~cg0Rl_|A*Cg|W6C!_QUi@7oPPIe:1eGQ!aTd3R(ZA#FFMMt2');
define('NONCE_SALT',       'SP38CZ_o0+)"OMVN_049Jxs1:P&aKp7;i&VJd+IQY3Py!~M@;E&&uy)fn*uKHDC:');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_hiq234_';

/**
 * Limits total Post Revisions saved per Post/Page.
 * Change or comment this line out if you would like to increase or remove the limit.
 */
define('WP_POST_REVISIONS',  10);

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', '');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/**
 * Removing this could cause issues with your experience in the DreamHost panel
 */

if (isset($_SERVER['HTTP_HOST']) && preg_match("/^(.*)\.dream\.website$/", $_SERVER['HTTP_HOST'])) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        define('WP_SITEURL', $proto . '://' . $_SERVER['HTTP_HOST']);
        define('WP_HOME',    $proto . '://' . $_SERVER['HTTP_HOST']);
        define('JETPACK_STAGING_MODE', true);
}

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
