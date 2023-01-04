<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class IniConfigReader
{
    public function get(string $key): string
    {
        return (string) \ini_get($key);
    }
}
