<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductContentLayoutDefinition::class)]
class ProductContentLayoutDefinitionTest extends TestCase
{
    public function testProvidesConfiguratorSettingsAsPageDataRequirement(): void
    {
        $requirements = (new ProductContentLayoutDefinition())->getPageDataRequirements();
        $configuratorSettings = null;
        foreach ($requirements as $requirement) {
            if ($requirement->key === 'configuratorSettings') {
                $configuratorSettings = $requirement;
                break;
            }
        }

        static::assertInstanceOf(DataRequirement::class, $configuratorSettings);
        static::assertSame(ProductConfiguratorDataLoader::SOURCE, $configuratorSettings->source);
        static::assertEquals(
            new ProductConfiguratorLoaderConfig(productId: 'productId'),
            $configuratorSettings->config,
        );
    }
}
