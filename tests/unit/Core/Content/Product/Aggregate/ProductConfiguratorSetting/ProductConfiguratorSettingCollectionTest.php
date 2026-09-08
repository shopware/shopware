<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductConfiguratorSetting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductConfiguratorSettingCollection::class)]
class ProductConfiguratorSettingCollectionTest extends TestCase
{
    public function testGroupedOptionsGroupsByPropertyGroupAndSkipsSettingsWithoutOption(): void
    {
        $collection = new ProductConfiguratorSettingCollection([
            $this->createSetting('setting-red', 'color-group', 'option-red'),
            $this->createSetting('setting-blue', 'color-group', 'option-blue'),
            $this->createSetting('setting-l', 'size-group', 'option-l'),
            $this->createSettingWithoutOption('setting-empty'),
        ]);

        $groups = $collection->getGroupedOptions();

        static::assertCount(2, $groups);
        $colorGroup = $groups->get('color-group');
        static::assertInstanceOf(PropertyGroupEntity::class, $colorGroup);
        static::assertNotNull($colorGroup->getOptions());
        static::assertCount(2, $colorGroup->getOptions());
    }

    public function testGetByOptionIdFindsTheMatchingSetting(): void
    {
        $match = $this->createSetting('setting-red', 'color-group', 'option-red');
        $collection = new ProductConfiguratorSettingCollection([
            $match,
            $this->createSetting('setting-l', 'size-group', 'option-l'),
        ]);

        static::assertSame($match, $collection->getByOptionId('option-red'));
        static::assertNull($collection->getByOptionId('unknown'));
    }

    public function testFiltersByProductAndOptionId(): void
    {
        $red = $this->createSetting('setting-red', 'color-group', 'option-red');
        $red->setProductId('product-a');
        $large = $this->createSetting('setting-l', 'size-group', 'option-l');
        $large->setProductId('product-b');

        $collection = new ProductConfiguratorSettingCollection([$red, $large]);

        static::assertSame(['product-a', 'product-b'], array_values($collection->getProductIds()));
        static::assertSame(['option-red', 'option-l'], array_values($collection->getOptionIds()));
        static::assertSame(['setting-red'], $collection->filterByProductId('product-a')->getKeys());
        static::assertSame(['setting-l'], $collection->filterByOptionId('option-l')->getKeys());
    }

    public function testGetOptionsCollectsTheAssignedOptions(): void
    {
        $collection = new ProductConfiguratorSettingCollection([
            $this->createSetting('setting-red', 'color-group', 'option-red'),
            $this->createSettingWithoutOption('setting-empty'),
        ]);

        static::assertSame(['option-red'], $collection->getOptions()->getKeys());
    }

    private function createSetting(string $id, string $groupId, string $optionId): ProductConfiguratorSettingEntity
    {
        $group = new PropertyGroupEntity();
        $group->setId($groupId);

        $option = new PropertyGroupOptionEntity();
        $option->setId($optionId);
        $option->setGroupId($groupId);
        $option->setGroup($group);

        $setting = new ProductConfiguratorSettingEntity();
        $setting->setId($id);
        $setting->setOptionId($optionId);
        $setting->setOption($option);

        return $setting;
    }

    private function createSettingWithoutOption(string $id): ProductConfiguratorSettingEntity
    {
        $setting = new ProductConfiguratorSettingEntity();
        $setting->setId($id);
        $setting->setOptionId('missing');

        return $setting;
    }
}
