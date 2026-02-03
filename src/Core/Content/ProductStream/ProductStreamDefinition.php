<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\ProductExport\ProductExportDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductStreamDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_stream';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getDefaults(): array
    {
        return [
            'internal' => false,
        ];
    }

    public function getCollectionClass(): string
    {
        return ProductStreamCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductStreamEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductStreamHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of product stream.'),
            (new JsonField('api_filter', 'apiFilter'))->addFlags(new WriteProtected())->setDescription('Internal field.'),
            (new BoolField('invalid', 'invalid'))->addFlags(new WriteProtected())->setDescription('When the boolean value is `true`, the ProductStream is no more available for usage.'),

            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new BoolField('internal', 'internal'))->addFlags(new ApiAware())->setDescription('When the boolean value is `true` indicating that it is for internal use only and will not appear in product stream listings.'),

            (new TranslationsAssociationField(ProductStreamTranslationDefinition::class, 'product_stream_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('filters', ProductStreamFilterDefinition::class, 'product_stream_id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('productCrossSellings', ProductCrossSellingDefinition::class, 'product_stream_id'),
            new OneToManyAssociationField('productExports', ProductExportDefinition::class, 'product_stream_id', 'id'),
            new OneToManyAssociationField('categories', CategoryDefinition::class, 'product_stream_id'),
        ]);
    }
}
