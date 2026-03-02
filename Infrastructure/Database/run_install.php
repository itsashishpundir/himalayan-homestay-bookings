<?php
require_once dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php';
\Himalayan\Homestay\Infrastructure\Database\Installer::install();
echo "Schema updated!\n";
