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
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductConfiguratorLoader::class)]
class ProductConfiguratorLoaderTest extends TestCase
{
    public function testLoadOrdersGroupsNotCoveredByTheIndividualConfigurationByPosition(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $groupAId = Uuid::randomHex();
        $groupBId = Uuid::randomHex();
        $groupCId = Uuid::randomHex();

        $optionAId = Uuid::randomHex();
        $optionBId = Uuid::randomHex();
        $optionCId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$optionAId, $optionBId, $optionCId], true);

        $combinationLoader = static::createStub(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        // the option order deliberately does not match the group positions
        $optionRepository = new StaticEntityRepository([
            new PropertyGroupOptionCollection([
                $this->buildOption($optionCId, 'c', $groupCId, 'c', 3, settingPosition: 1),
                $this->buildOption($optionAId, 'a', $groupAId, 'a', 1, settingPosition: 1),
                $this->buildOption($optionBId, 'b', $groupBId, 'b', 2, settingPosition: 1),
            ]),
        ]);

        $loader = new ProductConfiguratorLoader($combinationLoader, $optionRepository);

        $product = new SalesChannelProductEntity();
        $product->setId($variantId);
        $product->setParentId($parentId);
        $product->setOptionIds([$optionAId, $optionBId, $optionCId]);
        // only one group is sorted individually, the remaining ones must follow by group position
        $product->setVariantListingConfig(new VariantListingConfig(null, null, [
            [
                'id' => $groupBId,
                'representation' => 'box',
                'expressionForListings' => false,
            ],
        ]));

        $groups = $loader->load($product, $context);

        static::assertSame([$groupBId, $groupAId, $groupCId], array_values($groups->getIds()));
    }

    /**
     * When the current variant carries an option id without a
     * `product_configurator_setting` row (here: Blue), the sibling options must
     * stay combinable and the setting-less option must be surfaced from the
     * variant combinations.
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

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$redOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$redOptionId, $sizeMediumId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeMediumId], true);

        $combinationLoader = static::createStub(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        $optionRepository = new StaticEntityRepository([
            new PropertyGroupOptionCollection([
                $this->buildOption($redOptionId, 'Red', $colorGroupId, 'Color', 1, settingPosition: 1),
                $this->buildOption($blueOptionId, 'Blue', $colorGroupId, 'Color', 1),
                $this->buildOption($sizeSmallId, 'S', $sizeGroupId, 'Size', 2, settingPosition: 1),
                $this->buildOption($sizeMediumId, 'M', $sizeGroupId, 'Size', 2, settingPosition: 2),
            ]),
        ]);

        $loader = new ProductConfiguratorLoader($combinationLoader, $optionRepository);

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

    public function testNoOptionLookupWhenProductHasNoCombinations(): void
    {
        $context = Generator::generateSalesChannelContext();

        $combinationLoader = static::createStub(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn(new AvailableCombinationResult());

        // Without combinations there is nothing to display, so the option
        // repository must not be queried at all. The empty static repository
        // throws on an unexpected search, doubling as the assertion.
        $optionRepository = new StaticEntityRepository([]);

        $loader = new ProductConfiguratorLoader($combinationLoader, $optionRepository);

        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setParentId(Uuid::randomHex());

        $groups = $loader->load($product, $context);

        static::assertCount(0, $groups);
    }

    public function testConfiguratorStaysHiddenWhenProductHasNoConfiguratorSettingsAtAll(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $colorGroupId = Uuid::randomHex();
        $sizeGroupId = Uuid::randomHex();

        $redOptionId = Uuid::randomHex();
        $sizeSmallId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$redOptionId, $sizeSmallId], true);

        $combinationLoader = static::createStub(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        // No option of the product carries a configurator setting: variants were
        // deliberately created without a configurator (supported API pattern), so
        // the product detail page must not render one.
        $optionRepository = new StaticEntityRepository([
            new PropertyGroupOptionCollection([
                $this->buildOption($redOptionId, 'Red', $colorGroupId, 'Color', 1),
                $this->buildOption($sizeSmallId, 'S', $sizeGroupId, 'Size', 2),
            ]),
        ]);

        $loader = new ProductConfiguratorLoader($combinationLoader, $optionRepository);

        $product = new SalesChannelProductEntity();
        $product->setId($variantId);
        $product->setParentId($parentId);
        $product->setOptionIds([$redOptionId, $sizeSmallId]);

        $groups = $loader->load($product, $context);

        static::assertCount(
            0,
            $groups,
            'A product without any configurator setting must not render a configurator.'
        );
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

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$redOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeSmallId], true);

        $combinationLoader = static::createStub(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        // The Blue option carries no group association and therefore cannot be
        // assigned to a configurator group.
        $blueOption = new PropertyGroupOptionEntity();
        $blueOption->setId($blueOptionId);
        $blueOption->setName('Blue');
        $blueOption->setTranslated(['name' => 'Blue']);

        $optionRepository = new StaticEntityRepository([
            new PropertyGroupOptionCollection([
                $this->buildOption($redOptionId, 'Red', $colorGroupId, 'Color', 1, settingPosition: 1),
                $this->buildOption($sizeSmallId, 'S', $sizeGroupId, 'Size', 2, settingPosition: 1),
                $blueOption,
            ]),
        ]);

        $loader = new ProductConfiguratorLoader($combinationLoader, $optionRepository);

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

    public function testGroupWithoutAnySettingIsSurfacedFromVariantOptions(): void
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

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$redOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$redOptionId, $sizeMediumId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeSmallId], true);
        $combinationResult->addCombination([$blueOptionId, $sizeMediumId], true);

        $combinationLoader = static::createStub(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        // The Color group has no configurator settings at all - only the Size
        // group is configured.
        $optionRepository = new StaticEntityRepository([
            new PropertyGroupOptionCollection([
                $this->buildOption($redOptionId, 'Red', $colorGroupId, 'Color', 1),
                $this->buildOption($blueOptionId, 'Blue', $colorGroupId, 'Color', 1),
                $this->buildOption($sizeSmallId, 'S', $sizeGroupId, 'Size', 2, settingPosition: 1),
                $this->buildOption($sizeMediumId, 'M', $sizeGroupId, 'Size', 2, settingPosition: 2),
            ]),
        ]);

        $loader = new ProductConfiguratorLoader($combinationLoader, $optionRepository);

        $product = new SalesChannelProductEntity();
        $product->setId($variantId);
        $product->setParentId($parentId);
        $product->setOptionIds([$blueOptionId, $sizeSmallId]);

        $groups = $loader->load($product, $context);

        $colorGroup = $groups->get($colorGroupId);
        static::assertInstanceOf(
            PropertyGroupEntity::class,
            $colorGroup,
            'A group without any configurator setting must be surfaced when its options occur on variants.'
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

    private function buildOption(
        string $optionId,
        string $optionName,
        string $groupId,
        string $groupName,
        int $groupPosition,
        ?int $settingPosition = null
    ): PropertyGroupOptionEntity {
        $group = new PropertyGroupEntity();
        $group->setId($groupId);
        $group->setName($groupName);
        $group->setTranslated(['name' => $groupName]);
        $group->setPosition($groupPosition);
        $group->setSortingType(PropertyGroupDefinition::SORTING_TYPE_POSITION);

        $option = new PropertyGroupOptionEntity();
        $option->setId($optionId);
        $option->setName($optionName);
        $option->setTranslated(['name' => $optionName]);
        $option->setGroupId($groupId);
        $option->setGroup($group);

        if ($settingPosition !== null) {
            $setting = new ProductConfiguratorSettingEntity();
            $setting->setUniqueIdentifier(Uuid::randomHex());
            $setting->setId(Uuid::randomHex());
            $setting->setOptionId($optionId);
            $setting->setPosition($settingPosition);

            $option->setProductConfiguratorSettings(new ProductConfiguratorSettingCollection([$setting]));
        }

        return $option;
    }
}
