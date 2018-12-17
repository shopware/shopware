<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CatalogField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'product_translation';
    }

    public static function isVersionAware(): bool
    {
        return true;
    }

    public static function getCollectionClass(): string
    {
        return ProductTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ProductTranslationEntity::class;
    }

    public static function getDefinition(): string
    {
        return ProductDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('additional_text', 'additionalText'),
            new StringField('name', 'name'),
            new LongTextField('keywords', 'keywords'),
            new LongTextField('description', 'description'),
            new LongTextWithHtmlField('description_long', 'descriptionLong'),
            new StringField('meta_title', 'metaTitle'),
            new StringField('pack_unit', 'packUnit'),

            new CatalogField(),
            new ManyToOneAssociationField('catalog', 'catalog_id', CatalogDefinition::class, false, 'id'),
        ]);
    }
}
