<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingLoaderConfig;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductContentLayoutDefinition::class)]
class ProductContentLayoutDefinitionTest extends TestCase
{
    public function testProductEntityRequirementExplicitlyLoadsCoverMedia(): void
    {
        $requirements = (new ProductContentLayoutDefinition())->getPageDataRequirements();
        $product = array_values(array_filter($requirements, static fn (DataRequirement $requirement): bool => $requirement->key === 'product'));

        static::assertCount(1, $product);
        static::assertInstanceOf(EntityLoaderConfig::class, $product[0]->config);
        static::assertContains('cover.media', $product[0]->config->associations);
    }

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

    public function testProvidesCrossSellingsAsPageDataRequirement(): void
    {
        $requirements = (new ProductContentLayoutDefinition())->getPageDataRequirements();
        $crossSellings = array_values(array_filter($requirements, static fn (DataRequirement $requirement): bool => $requirement->key === 'crossSellings'));

        static::assertCount(1, $crossSellings);
        static::assertSame(CrossSellingDataLoader::SOURCE, $crossSellings[0]->source);
        static::assertEquals(new CrossSellingLoaderConfig(property: 'productId'), $crossSellings[0]->config);
    }
}
