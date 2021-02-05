<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation;

use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductFeatureSetTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = ProductFeatureSetDefinition::ENTITY_NAME . '_translation';

    public function getCollectionClass(): string
    {
        return ProductFeatureSetTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductFeatureSetTranslationEntity::class;
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getParentDefinitionClass(): string
    {
        return ProductFeatureSetDefinition::class;
    }

    public function since(): ?string
    {
        return '6.3.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new StringField('description', 'description'))->addFlags(new ApiAware()),
        ]);
    }
}
