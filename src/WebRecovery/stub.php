<?php declare(strict_types=1);

error_reporting(-1);

ignore_user_abort(true);

if (function_exists('ini_set')) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    @ini_set('opcache.enable_cli', '0');
    @ini_set('max_execution_time', '300');
}

if (\PHP_VERSION_ID < 80100) {
    echo 'PHP 8.1 is required.';

    http_response_code(500);
    exit(1);
}

if (!extension_loaded('Phar')) {
    die('The PHP Phar extension is not enabled.');
}

if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get')) {
    date_default_timezone_set('UTC');
}

if ('cli' === \PHP_SAPI || !isset($_SERVER['REQUEST_URI'])) {
    Phar::mapPhar('shopware-recovery.phar');

    if (($_SERVER['argv'][1] ?? '') === 'composer') {
        unset($_SERVER['argv'][0]);
        $_SERVER['argv'] = array_values($_SERVER['argv']);

        $_SERVER['argc'] = count($_SERVER['argv']);
        $argc = &$_SERVER['argc'];
        $argv = &$_SERVER['argv'];
        require 'phar://shopware-recovery.phar/vendor/bin/composer';
    } else {
        require 'phar://shopware-recovery.phar/bin/console';
    }
} else {
    function rewrites(): bool|string
    {
        /** @var non-empty-string $separator */
        $separator = basename(__FILE__);
        [,$url] = explode($separator, $_SERVER['REQUEST_URI'], 2);

        if (strpos($url, '..')) {
            return false;
        }

        if (!empty($url) && is_file('phar://' . __FILE__ . '/src/Resources/public/' . $url)) {
            return '/src/Resources/public' . $url;
        }

        return 'index.php';
    }

    Phar::webPhar(
        null,
        'index.php',
        null,
        [
            'php' => Phar::PHP,
            'css' => 'text/css',
            'js' => 'application/x-javascript',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'json' => 'application/json',
        ],
        'rewrites',
    );
}

__HALT_COMPILER();
