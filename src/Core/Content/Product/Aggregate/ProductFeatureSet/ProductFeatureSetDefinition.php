<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductFeatureSet;

use Shopware\Core\Content\Product\Aggregate\ProductFeature\ProductFeatureDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductFeatureSetDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'product_feature_set';

    public const TYPE_PRODUCT_ATTRIBUTE = 'product';
    public const TYPE_PRODUCT_PROPERTY = 'property';
    public const TYPE_PRODUCT_CUSTOM_FIELD = 'customField';
    public const TYPE_PRODUCT_REFERENCE_PRICE = 'referencePrice';

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
            (new ManyToManyAssociationField('products', ProductDefinition::class, ProductFeatureDefinition::class, 'product_feature_set_id', 'product_id'))
                ->addFlags(new CascadeDelete(), new ReverseInherited('featureSets')),
            (new TranslationsAssociationField(ProductFeatureSetTranslationDefinition::class, 'product_feature_set_id')),
        ]);
    }
}
