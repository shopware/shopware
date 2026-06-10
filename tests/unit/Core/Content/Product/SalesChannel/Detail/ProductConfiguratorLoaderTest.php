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
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(ProductConfiguratorLoader::class)]
class ProductConfiguratorLoaderTest extends TestCase
{
    public function testSortSettingsOrdersRemainingGroupsByPositionWhenConfigIsPartial(): void
    {
        /** @var StaticEntityRepository<ProductConfiguratorSettingCollection> $configuratorRepository */
        $configuratorRepository = new StaticEntityRepository([]);
        /** @var StaticEntityRepository<PropertyGroupOptionCollection> $optionRepository */
        $optionRepository = new StaticEntityRepository([]);

        $loader = new ProductConfiguratorLoader(
            $configuratorRepository,
            $this->createMock(AbstractAvailableCombinationLoader::class),
            $optionRepository,
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
     * options. The loader now surfaces the setting-less Blue option from the
     * actual variant combinations, so the current selection includes it and
     * every combination resolves against real variant availability.
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

        /** @var StaticEntityRepository<ProductConfiguratorSettingCollection> $configuratorRepository */
        $configuratorRepository = new StaticEntityRepository([
            new ProductConfiguratorSettingCollection([
                $this->buildConfiguratorSetting($redOptionId, 'Red', $colorGroupId, 'Color', 1),
                $this->buildConfiguratorSetting($sizeSmallId, 'S', $sizeGroupId, 'Size', 2),
                $this->buildConfiguratorSetting($sizeMediumId, 'M', $sizeGroupId, 'Size', 2),
            ]),
        ]);

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$redOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$redOptionId, $sizeMediumId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeMediumId], true);

        $combinationLoader = $this->createMock(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        // The Blue option has no configurator setting, so the loader resolves it
        // from the option repository to surface it back into the Color group.
        /** @var StaticEntityRepository<PropertyGroupOptionCollection> $optionRepository */
        $optionRepository = new StaticEntityRepository([
            new PropertyGroupOptionCollection([
                $this->buildOptionWithGroup($blueOptionId, 'Blue', $colorGroupId, 'Color'),
            ]),
        ]);

        $loader = new ProductConfiguratorLoader($configuratorRepository, $combinationLoader, $optionRepository);

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

        $blueOptionResolved = $colorOptions->get($blueOptionId);
        static::assertInstanceOf(
            PropertyGroupOptionEntity::class,
            $blueOptionResolved,
            'The Blue option must be surfaced into the Color group even without a configurator setting.'
        );
        static::assertTrue(
            $blueOptionResolved->getCombinable(),
            'The surfaced Blue option must be selectable since Blue variants exist in stock.'
        );
    }

    public function testNoOptionLookupWhenAllCombinationOptionsHaveConfiguratorSettings(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $colorGroupId = Uuid::randomHex();
        $sizeGroupId = Uuid::randomHex();

        $redOptionId = Uuid::randomHex();
        $blueOptionId = Uuid::randomHex();
        $sizeSmallId = Uuid::randomHex();
        $sizeMediumId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        /** @var StaticEntityRepository<ProductConfiguratorSettingCollection> $configuratorRepository */
        $configuratorRepository = new StaticEntityRepository([
            new ProductConfiguratorSettingCollection([
                $this->buildConfiguratorSetting($redOptionId, 'Red', $colorGroupId, 'Color', 1),
                $this->buildConfiguratorSetting($blueOptionId, 'Blue', $colorGroupId, 'Color', 1),
                $this->buildConfiguratorSetting($sizeSmallId, 'S', $sizeGroupId, 'Size', 2),
                $this->buildConfiguratorSetting($sizeMediumId, 'M', $sizeGroupId, 'Size', 2),
            ]),
        ]);

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$redOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$redOptionId, $sizeMediumId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeMediumId], true);

        $combinationLoader = $this->createMock(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        // Every combination option id is covered by a configurator setting, so the
        // option repository must not be queried at all. The empty static repository
        // throws on an unexpected search, doubling as the assertion.
        /** @var StaticEntityRepository<PropertyGroupOptionCollection> $optionRepository */
        $optionRepository = new StaticEntityRepository([]);

        $loader = new ProductConfiguratorLoader($configuratorRepository, $combinationLoader, $optionRepository);

        $product = new SalesChannelProductEntity();
        $product->setId($variantId);
        $product->setParentId($parentId);
        $product->setOptionIds([$blueOptionId, $sizeSmallId]);

        $groups = $loader->load($product, $context);

        static::assertCount(2, $groups);
        foreach ($groups as $group) {
            foreach ($group->getOptions() ?? [] as $option) {
                static::assertTrue($option->getCombinable());
            }
        }
    }

    public function testOptionWithoutGroupAssociationIsNotSurfaced(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $colorGroupId = Uuid::randomHex();
        $sizeGroupId = Uuid::randomHex();

        $redOptionId = Uuid::randomHex();
        $blueOptionId = Uuid::randomHex(); // missing from product_configurator_setting
        $sizeSmallId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        /** @var StaticEntityRepository<ProductConfiguratorSettingCollection> $configuratorRepository */
        $configuratorRepository = new StaticEntityRepository([
            new ProductConfiguratorSettingCollection([
                $this->buildConfiguratorSetting($redOptionId, 'Red', $colorGroupId, 'Color', 1),
                $this->buildConfiguratorSetting($sizeSmallId, 'S', $sizeGroupId, 'Size', 2),
            ]),
        ]);

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$redOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeSmallId], true);

        $combinationLoader = $this->createMock(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        // The resolved option carries no group association and therefore cannot be
        // assigned to a configurator group.
        $blueOption = new PropertyGroupOptionEntity();
        $blueOption->setId($blueOptionId);
        $blueOption->setName('Blue');
        $blueOption->setTranslated(['name' => 'Blue']);

        /** @var StaticEntityRepository<PropertyGroupOptionCollection> $optionRepository */
        $optionRepository = new StaticEntityRepository([
            new PropertyGroupOptionCollection([$blueOption]),
        ]);

        $loader = new ProductConfiguratorLoader($configuratorRepository, $combinationLoader, $optionRepository);

        $product = new SalesChannelProductEntity();
        $product->setId($variantId);
        $product->setParentId($parentId);
        $product->setOptionIds([$blueOptionId, $sizeSmallId]);

        $groups = $loader->load($product, $context);

        $colorGroup = $groups->get($colorGroupId);
        static::assertInstanceOf(PropertyGroupEntity::class, $colorGroup);
        $colorOptions = $colorGroup->getOptions();
        static::assertNotNull($colorOptions);
        static::assertNull(
            $colorOptions->get($blueOptionId),
            'An option without group association must not be surfaced into the configurator.'
        );
    }

    public function testMissingGroupIsCreatedWhenSurfacingOptions(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $colorGroupId = Uuid::randomHex();
        $sizeGroupId = Uuid::randomHex();

        $redOptionId = Uuid::randomHex();
        $blueOptionId = Uuid::randomHex();
        $sizeSmallId = Uuid::randomHex();
        $sizeMediumId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        // The Color group has no configurator settings at all - only the Size group
        // is configured.
        /** @var StaticEntityRepository<ProductConfiguratorSettingCollection> $configuratorRepository */
        $configuratorRepository = new StaticEntityRepository([
            new ProductConfiguratorSettingCollection([
                $this->buildConfiguratorSetting($sizeSmallId, 'S', $sizeGroupId, 'Size', 2),
                $this->buildConfiguratorSetting($sizeMediumId, 'M', $sizeGroupId, 'Size', 2),
            ]),
        ]);

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$redOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$redOptionId, $sizeMediumId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeMediumId], true);

        $combinationLoader = $this->createMock(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        /** @var StaticEntityRepository<PropertyGroupOptionCollection> $optionRepository */
        $optionRepository = new StaticEntityRepository([
            new PropertyGroupOptionCollection([
                $this->buildOptionWithGroup($redOptionId, 'Red', $colorGroupId, 'Color'),
                $this->buildOptionWithGroup($blueOptionId, 'Blue', $colorGroupId, 'Color'),
            ]),
        ]);

        $loader = new ProductConfiguratorLoader($configuratorRepository, $combinationLoader, $optionRepository);

        $product = new SalesChannelProductEntity();
        $product->setId($variantId);
        $product->setParentId($parentId);
        $product->setOptionIds([$blueOptionId, $sizeSmallId]);

        $groups = $loader->load($product, $context);

        $colorGroup = $groups->get($colorGroupId);
        static::assertInstanceOf(
            PropertyGroupEntity::class,
            $colorGroup,
            'The Color group must be created when surfacing options of a group without any configurator setting.'
        );

        $colorOptions = $colorGroup->getOptions();
        static::assertNotNull($colorOptions);
        static::assertCount(2, $colorOptions);

        foreach ([$redOptionId, $blueOptionId] as $optionId) {
            $option = $colorOptions->get($optionId);
            static::assertInstanceOf(PropertyGroupOptionEntity::class, $option);
            static::assertTrue($option->getCombinable());
        }
    }

    private function buildOptionWithGroup(
        string $optionId,
        string $optionName,
        string $groupId,
        string $groupName
    ): PropertyGroupOptionEntity {
        $group = new PropertyGroupEntity();
        $group->setId($groupId);
        $group->setName($groupName);
        $group->setTranslated(['name' => $groupName]);

        $option = new PropertyGroupOptionEntity();
        $option->setId($optionId);
        $option->setName($optionName);
        $option->setTranslated(['name' => $optionName]);
        $option->setGroupId($groupId);
        $option->setGroup($group);

        return $option;
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
