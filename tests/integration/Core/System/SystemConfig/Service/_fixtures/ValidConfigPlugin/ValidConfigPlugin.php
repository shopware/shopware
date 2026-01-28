<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\ValidConfigPlugin;

use Shopware\Core\Framework\Plugin;

/**
 * @internal
 */
class ValidConfigPlugin extends Plugin
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
