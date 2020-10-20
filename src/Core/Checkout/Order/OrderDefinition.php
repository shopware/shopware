<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\Tag\TagDefinition;

class OrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new WriteProtected()),

            (new NumberRangeField('order_number', 'orderNumber'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            (new FkField('billing_address_id', 'billingAddressId', OrderAddressDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderAddressDefinition::class, 'billing_address_version_id'))->addFlags(new Required()),

            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),

            (new DateTimeField('order_date_time', 'orderDateTime'))->addFlags(new Required()),
            (new DateField('order_date', 'orderDate'))->addFlags(new WriteProtected()),
            new CartPriceField('price', 'price'),
            (new FloatField('amount_total', 'amountTotal'))->addFlags(new WriteProtected(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new FloatField('amount_net', 'amountNet'))->addFlags(new WriteProtected()),
            (new FloatField('position_price', 'positionPrice'))->addFlags(new WriteProtected()),
            (new StringField('tax_status', 'taxStatus'))->addFlags(new WriteProtected()),

            new CalculatedPriceField('shipping_costs', 'shippingCosts'),
            (new FloatField('shipping_total', 'shippingTotal'))->addFlags(new WriteProtected()),
            (new FloatField('currency_factor', 'currencyFactor'))->addFlags(new Required()),
            new StringField('deep_link_code', 'deepLinkCode'),

            new StringField('affiliate_code', 'affiliateCode'),
            new StringField('campaign_code', 'campaignCode'),

            new LongTextField('customer_comment', 'customerComment'),

            (new StateMachineStateField('state_id', 'stateId', OrderStates::STATE_MACHINE))->setFlags(new Required()),
            new ManyToOneAssociationField('stateMachineState', 'state_id', StateMachineStateDefinition::class, 'id', true),

            new ListField('rule_ids', 'ruleIds', StringField::class),

            new CustomFields(),

            (new OneToOneAssociationField('orderCustomer', 'id', 'order_id', OrderCustomerDefinition::class))->addFlags(new CascadeDelete(), new SearchRanking(0.5)),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false))->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('addresses', OrderAddressDefinition::class, 'order_id'))->addFlags(new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('deliveries', OrderDeliveryDefinition::class, 'order_id'))->addFlags(new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('lineItems', OrderLineItemDefinition::class, 'order_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('transactions', OrderTransactionDefinition::class, 'order_id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('documents', DocumentDefinition::class, 'order_id'),
            (new ManyToManyAssociationField('tags', TagDefinition::class, OrderTagDefinition::class, 'order_id', 'tag_id'))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
        ]);

        return $fields;
    }
}
