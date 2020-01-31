<?php declare(strict_types=1);

namespace Shopware\Recovery\Common;

class Utils
{
    /**
     * Clear opcode caches to make sure that the
     * updated files are used in the following requests.
     */
    public static function clearOpcodeCache(): void
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
    }

    public static function getBaseUrl()
    {
        $filename = (isset($_SERVER['SCRIPT_FILENAME']))
                ? basename($_SERVER['SCRIPT_FILENAME'])
                : '';
        $baseUrl = $_SERVER['SCRIPT_NAME'];
        if (empty($baseUrl)) {
            return '';
        }
        if (basename($baseUrl) === $filename) {
            $basePath = dirname($baseUrl);
        } else {
            $basePath = $baseUrl;
        }
        if (mb_strpos(PHP_OS, 'WIN') === 0) {
            $basePath = str_replace('\\', '/', $basePath);
        }
        $basePath = rtrim($basePath, '/') . '/';

        return $basePath;
    }
}
