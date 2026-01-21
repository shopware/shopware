<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\BrokenConfigPlugin;

use Shopware\Core\Framework\Plugin;

/**
 * @internal
 */
class BrokenConfigPlugin extends Plugin
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
