<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'product_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isVersionAware(): bool
    {
        return true;
    }

    public function getCollectionClass(): string
    {
        return ProductTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductTranslationEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return ProductDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('meta_description', 'metaDescription'),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('keywords', 'keywords'),
            (new LongTextField('description', 'description'))->addFlags(new AllowHtml()),
            new StringField('meta_title', 'metaTitle'),
            new StringField('pack_unit', 'packUnit'),
            new StringField('pack_unit_plural', 'packUnitPlural'),
            new ListField('custom_search_keywords', 'customSearchKeywords'),

            new CustomFields(),
        ]);
    }
}
