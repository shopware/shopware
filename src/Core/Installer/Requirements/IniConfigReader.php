<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements;

/**
 * @package core
 * Extracted to be able to mock all ini values
 *
 * @internal
 */
class IniConfigReader
{
    public function get(string $key): string
    {
        return (string) \ini_get($key);
    }
}
