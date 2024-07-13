<?php

declare(strict_types=1);

require 'vendor/autoload.php';

date_default_timezone_set('Asia/Tashkent');

$update = json_decode(file_get_contents('php://input'));

if (isset($update)) {
    require 'bot.php';
    return;
}

require 'view/dashboard.php';