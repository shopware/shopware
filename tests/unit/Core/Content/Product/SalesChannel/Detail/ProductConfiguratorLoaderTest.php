<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * @internal
 */
#[CoversClass(ProductConfiguratorLoader::class)]
class ProductConfiguratorLoaderTest extends TestCase
{
    public function testSortSettingsOrdersRemainingGroupsByPositionWhenConfigIsPartial(): void
    {
        $loader = new ProductConfiguratorLoader(
            $this->createMock(EntityRepository::class),
            $this->createMock(AbstractAvailableCombinationLoader::class),
        );

        $product = new SalesChannelProductEntity();
        $product->setVariantListingConfig(new VariantListingConfig(null, null, [
            [
                'id' => 'group-b',
                'representation' => 'box',
                'expressionForListings' => false,
            ],
        ]));

        $groups = [
            'group-c' => $this->createGroup('group-c', 'c', 3),
            'group-a' => $this->createGroup('group-a', 'a', 1),
            'group-b' => $this->createGroup('group-b', 'b', 2),
        ];

        $method = new \ReflectionMethod(ProductConfiguratorLoader::class, 'sortSettings');

        $sorted = $method->invoke($loader, $groups, $product);
        static::assertInstanceOf(PropertyGroupCollection::class, $sorted);

        static::assertSame(['group-b', 'group-a', 'group-c'], array_values($sorted->getIds()));
    }

    private function createGroup(string $id, string $name, int $position): PropertyGroupEntity
    {
        $group = new PropertyGroupEntity();
        $group->setId($id);
        $group->setName($name);
        $group->setPosition($position);

        return $group;
    }
}
