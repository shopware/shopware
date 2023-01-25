<?php declare(strict_types=1);

namespace Shopware\Core\System\Tag;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTag\ShippingMethodTagDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTag\LandingPageTagDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipientTag\NewsletterRecipientTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class TagDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'tag';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TagCollection::class;
    }

    public function getEntityClass(): string
    {
        return TagEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            // reverse side of the associations, not available in sales-channel-api
            (new ManyToManyAssociationField('products', ProductDefinition::class, ProductTagDefinition::class, 'tag_id', 'product_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('media', MediaDefinition::class, MediaTagDefinition::class, 'tag_id', 'media_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('categories', CategoryDefinition::class, CategoryTagDefinition::class, 'tag_id', 'category_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('customers', CustomerDefinition::class, CustomerTagDefinition::class, 'tag_id', 'customer_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('orders', OrderDefinition::class, OrderTagDefinition::class, 'tag_id', 'order_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, ShippingMethodTagDefinition::class, 'tag_id', 'shipping_method_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('newsletterRecipients', NewsletterRecipientDefinition::class, NewsletterRecipientTagDefinition::class, 'tag_id', 'newsletter_recipient_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('landingPages', LandingPageDefinition::class, LandingPageTagDefinition::class, 'tag_id', 'landing_page_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('rules', RuleDefinition::class, RuleTagDefinition::class, 'tag_id', 'rule_id'))->addFlags(new CascadeDelete()),
        ]);

        return $collection;
    }
}
