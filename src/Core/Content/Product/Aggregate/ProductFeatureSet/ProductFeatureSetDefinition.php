<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductFeatureSet;

use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductFeatureSetDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'product_feature_set';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductFeatureSetCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductFeatureSetEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            new TranslatedField('description'),
            new JsonField('features', 'features'),
            (new TranslationsAssociationField(ProductFeatureSetTranslationDefinition::class, 'product_essential_characteristics_template_id')),
        ]);
    }
}
