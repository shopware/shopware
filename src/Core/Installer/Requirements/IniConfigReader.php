<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements;

/**
 * Extracted to be able to mock all ini values
 */
class IniConfigReader
{
    public function get(string $key): string
    {
        return (string) \ini_get($key);
    }
}
