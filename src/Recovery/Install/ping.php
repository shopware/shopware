<?php declare(strict_types=1);

header('HTTP/1.1 200 OK');
//no  cache headers
header('Expires: Mon, 26 Jul 1990 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

echo 'pong';
