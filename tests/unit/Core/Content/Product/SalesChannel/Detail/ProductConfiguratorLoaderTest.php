<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingEntity;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

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

    /**
     * Regression test for https://github.com/shopware/shopware/issues/14616.
     *
     * Reproduces the reported scenario:
     * - Product has two option groups: Color (Red, Blue) and Size (S, M).
     * - Variants exist for every Color/Size combination and all are in stock
     *   on clearance.
     * - The `product_configurator_setting` row for the "Blue" color has been
     *   removed (e.g. via direct database modification, or never written by an
     *   API client), so only Red, S and M are registered as configurator
     *   settings.
     * - The current variant is a Blue variant.
     *
     * Previously the Size group would grey out every option, because the
     * stored variant combinations (which still carried the Blue option id)
     * could never be matched against the hash built from the configurator
     * options. Availability now falls back to the actual variant
     * stock / clearance state.
     */
    public function testSizeOptionsRemainCombinableWhenCurrentVariantCarriesUnsetConfiguratorOption(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $colorGroupId = Uuid::randomHex();
        $sizeGroupId = Uuid::randomHex();

        $redOptionId = Uuid::randomHex();
        $blueOptionId = Uuid::randomHex(); // missing from product_configurator_setting
        $sizeSmallId = Uuid::randomHex();
        $sizeMediumId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        $configuratorRepository = $this->createMock(EntityRepository::class);
        $configuratorRepository->method('search')->willReturn(
            new EntitySearchResult(
                'product_configurator_setting',
                3,
                new ProductConfiguratorSettingCollection([
                    $this->buildConfiguratorSetting($redOptionId, 'Red', $colorGroupId, 'Color', 1),
                    $this->buildConfiguratorSetting($sizeSmallId, 'S', $sizeGroupId, 'Size', 2),
                    $this->buildConfiguratorSetting($sizeMediumId, 'M', $sizeGroupId, 'Size', 2),
                ]),
                null,
                new Criteria(),
                $context->getContext()
            )
        );

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$redOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$redOptionId, $sizeMediumId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeMediumId], true);

        $combinationLoader = $this->createMock(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        $loader = new ProductConfiguratorLoader($configuratorRepository, $combinationLoader);

        $product = new SalesChannelProductEntity();
        $product->setId($variantId);
        $product->setParentId($parentId);
        $product->setOptionIds([$blueOptionId, $sizeSmallId]);

        $groups = $loader->load($product, $context);

        $sizeGroup = $groups->get($sizeGroupId);
        static::assertInstanceOf(PropertyGroupEntity::class, $sizeGroup);

        $sizeOptions = $sizeGroup->getOptions();
        static::assertNotNull($sizeOptions);

        $smallOption = $sizeOptions->get($sizeSmallId);
        static::assertInstanceOf(PropertyGroupOptionEntity::class, $smallOption);
        static::assertTrue(
            $smallOption->getCombinable(),
            'Small size must remain combinable when the current variant has an option id without configurator setting.'
        );

        $mediumOption = $sizeOptions->get($sizeMediumId);
        static::assertInstanceOf(PropertyGroupOptionEntity::class, $mediumOption);
        static::assertTrue(
            $mediumOption->getCombinable(),
            'Medium size must remain combinable when the current variant has an option id without configurator setting.'
        );

        $colorGroup = $groups->get($colorGroupId);
        static::assertInstanceOf(PropertyGroupEntity::class, $colorGroup);
        $colorOptions = $colorGroup->getOptions();
        static::assertNotNull($colorOptions);

        $redOption = $colorOptions->get($redOptionId);
        static::assertInstanceOf(PropertyGroupOptionEntity::class, $redOption);
        static::assertTrue(
            $redOption->getCombinable(),
            'The Red option must be selectable since Red variants exist in stock.'
        );
    }

    private function createGroup(string $id, string $name, int $position): PropertyGroupEntity
    {
        $group = new PropertyGroupEntity();
        $group->setId($id);
        $group->setName($name);
        $group->setPosition($position);

        return $group;
    }

    private function buildConfiguratorSetting(
        string $optionId,
        string $optionName,
        string $groupId,
        string $groupName,
        int $position
    ): ProductConfiguratorSettingEntity {
        $group = new PropertyGroupEntity();
        $group->setId($groupId);
        $group->setName($groupName);
        $group->setTranslated(['name' => $groupName]);
        $group->setPosition($position);
        $group->setSortingType(PropertyGroupDefinition::SORTING_TYPE_POSITION);

        $option = new PropertyGroupOptionEntity();
        $option->setId($optionId);
        $option->setName($optionName);
        $option->setTranslated(['name' => $optionName]);
        $option->setGroupId($groupId);
        $option->setGroup($group);

        $setting = new ProductConfiguratorSettingEntity();
        $setting->setUniqueIdentifier(Uuid::randomHex());
        $setting->setId(Uuid::randomHex());
        $setting->setOptionId($optionId);
        $setting->setOption($option);
        $setting->setPosition($position);

        return $setting;
    }
}
