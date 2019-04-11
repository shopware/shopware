<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductManufacturerTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'product_manufacturer_translation';
    }

    public static function getCollectionClass(): string
    {
        return ProductManufacturerTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ProductManufacturerTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return ProductManufacturerDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextWithHtmlField('description', 'description'),

            new CustomFields(),
        ]);
    }
}
