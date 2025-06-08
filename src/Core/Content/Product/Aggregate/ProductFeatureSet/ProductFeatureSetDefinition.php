<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductFeatureSet;

use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductFeatureSetDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_feature_set';

    final public const TYPE_PRODUCT_ATTRIBUTE = 'product';
    final public const TYPE_PRODUCT_PROPERTY = 'property';
    final public const TYPE_PRODUCT_CUSTOM_FIELD = 'customField';
    final public const TYPE_PRODUCT_REFERENCE_PRICE = 'referencePrice';

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

    public function since(): ?string
    {
        return '6.3.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductFeatureSetHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            new TranslatedField('description'),
            new JsonField('features', 'features'),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'product_feature_set_id', 'id'))->addFlags(new SetNullOnDelete(), new ReverseInherited('featureSet')),
            (new TranslationsAssociationField(ProductFeatureSetTranslationDefinition::class, 'product_feature_set_id'))->addFlags(new Required()),
        ]);
    }
}
