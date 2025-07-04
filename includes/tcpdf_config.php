<?php
// Configuración TCPDF
define('K_TCPDF_EXTERNAL_CONFIG', true);
define('K_PATH_MAIN', dirname(__FILE__).'/');
define('K_PATH_URL', K_PATH_MAIN);
define('K_PATH_FONTS', K_PATH_MAIN.'fonts/');
define('K_PATH_CACHE', K_PATH_MAIN.'cache/');
define('K_PATH_URL_CACHE', K_PATH_URL.'cache/');
define('K_PATH_IMAGES', K_PATH_MAIN.'images/');
define('K_BLANK_IMAGE', K_PATH_IMAGES.'_blank.png');
define('PDF_FONT_NAME_MAIN', 'helvetica');
define('PDF_FONT_SIZE_MAIN', 10);
define('PDF_FONT_NAME_DATA', 'helvetica');
define('PDF_FONT_SIZE_DATA', 8);
define('PDF_FONT_MONOSPACED', 'courier');
define('PDF_IMAGE_SCALE_RATIO', 1.25);
define('HEAD_MAGNIFICATION', 1.1);
define('K_CELL_HEIGHT_RATIO', 1.25);
define('K_TITLE_MAGNIFICATION', 1.3);
define('K_SMALL_RATIO', 2/3);
define('K_THAI_TOPCHARS', true);
define('K_TCPDF_CALLS_IN_HTML', true);
define('K_TCPDF_THROW_EXCEPTION_ERROR', false);
define('K_TIMEZONE', 'America/Guayaquil');
?>