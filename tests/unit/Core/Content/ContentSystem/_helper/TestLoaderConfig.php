<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\_helper;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('discovery')]
final class TestLoaderConfig extends AbstractContentDataLoaderConfig
{
    public function getDecorated(): AbstractContentDataLoaderConfig
    {
        throw new DecorationPatternException(self::class);
    }
}
