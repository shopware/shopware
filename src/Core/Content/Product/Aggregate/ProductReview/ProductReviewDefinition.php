<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductReview;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class ProductReviewDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'product_review';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductReviewCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductReviewEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductReviewHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ProductDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class)),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new StringField('external_user', 'externalUser'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('external_email', 'externalEmail'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('title', 'title'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING)),
            (new LongTextField('content', 'content'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING), new AllowHtml()),
            (new FloatField('points', 'points'))->addFlags(new ApiAware()),
            (new BoolField('status', 'status'))->addFlags(new ApiAware()),
            (new LongTextField('comment', 'comment'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false)),
            (new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false)),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
