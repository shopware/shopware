<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\StateMachineTranslationDefinition;

class EntityDefinitionTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEntityDefinitionCompilation(): void
    {
        $definition = $this->getContainer()->get(ProductDefinition::class);

        static::assertContainsOnlyInstancesOf(Field::class, $definition->getFields());
        static::assertSame('product_manufacturer_version_id', $definition->getFields()->get('productManufacturerVersionId')->getStorageName());
        static::assertInstanceOf(ProductManufacturerDefinition::class, $definition->getFields()->get('productManufacturerVersionId')->getVersionReferenceDefinition());
        static::assertSame($this->getContainer()->get(ProductManufacturerDefinition::class), $definition->getFields()->get('productManufacturerVersionId')->getVersionReferenceDefinition());
    }

    public function testTranslationCompilation(): void
    {
        $definition = $this->getContainer()->get(ProductTranslationDefinition::class);

        static::assertContainsOnlyInstancesOf(Field::class, $definition->getFields());
        static::assertSame('language_id', $definition->getFields()->get('languageId')->getStorageName());
    }

    /**
     * @dataProvider provideTranslatedDefinitions
     */
    public function testTranslationsOnDefinitionsWithLanguageId(string $baseDefinitionClass, string $translationDefinitionClass): void
    {
        /** @var EntityDefinition $baseDefinition */
        $baseDefinition = $this->getContainer()->get($baseDefinitionClass);
        /** @var EntityTranslationDefinition $translationDefinition */
        $translationDefinition = $this->getContainer()->get($translationDefinitionClass);

        static::assertSame($translationDefinition, $baseDefinition->getTranslationDefinition());
        static::assertInstanceOf(JsonField::class, $baseDefinition->getFields()->get('translated'));
    }

    /**
     * @dataProvider provideTranslatedDefinitions
     */
    public function testTranslationsOnDefinitionsWithLanguageIdInOtherOrder(string $baseDefinitionClass, string $translationDefinitionClass): void
    {
        /** @var EntityDefinition $baseDefinition */
        $baseDefinition = $this->getContainer()->get($baseDefinitionClass);
        /** @var EntityTranslationDefinition $translationDefinition */
        $translationDefinition = $this->getContainer()->get($translationDefinitionClass);

        static::assertInstanceOf(JsonField::class, $baseDefinition->getFields()->get('translated'));
        static::assertSame($translationDefinition, $baseDefinition->getTranslationDefinition());
    }

    /**
     * @dataProvider provideTranslatedDefinitions
     */
    public function testTranslationParentDefinition(string $baseDefinitionClass, string $translationDefinitionClass): void
    {
        /** @var EntityDefinition $baseDefinition */
        $baseDefinition = $this->getContainer()->get($baseDefinitionClass);
        /** @var EntityTranslationDefinition $translationDefinition */
        $translationDefinition = $this->getContainer()->get($translationDefinitionClass);

        static::assertSame($baseDefinition->getClass(), $translationDefinition->getParentDefinition()->getClass());
        static::assertSame($baseDefinition, $translationDefinition->getParentDefinition());
    }

    public function provideTranslatedDefinitions(): array
    {
        return [
            [CmsPageDefinition::class, CmsPageTranslationDefinition::class],
            [CmsSlotDefinition::class, CmsSlotTranslationDefinition::class],
            [PropertyGroupDefinition::class, PropertyGroupTranslationDefinition::class],
            [StateMachineDefinition::class, StateMachineTranslationDefinition::class],
            [StateMachineStateDefinition::class, StateMachineStateTranslationDefinition::class],
            [ProductDefinition::class, ProductTranslationDefinition::class],
            [PromotionDefinition::class, PromotionTranslationDefinition::class],
        ];
    }
}
