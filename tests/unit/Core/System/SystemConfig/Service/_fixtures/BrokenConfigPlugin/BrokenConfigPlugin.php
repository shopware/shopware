<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Service\_fixtures\BrokenConfigPlugin;

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
