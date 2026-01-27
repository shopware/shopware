<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('checkout')]
class PaymentMethodDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'payment_method';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentMethodCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentMethodEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of payment method.'),
            (new FkField('plugin_id', 'pluginId', PluginDefinition::class))->setDescription('Unique identity of plugin.'),
            (new StringField('handler_identifier', 'handlerIdentifier'))->setDescription('Internal field that contains system identifier details for payment methods like Paypal.'),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('distinguishableName'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('The order of the tabs of your defined payment methods in the storefront by entering numerical values like 1,2,3, etc.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the payment methods are available for selection in the storefront.'),
            (new BoolField('after_order_enabled', 'afterOrderEnabled'))->addFlags(new ApiAware())->setDescription('When set to true, customers are redirected to the payment options page to choose a new payment method on order failure.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new FkField('availability_rule_id', 'availabilityRuleId', RuleDefinition::class))->setDescription('Unique identity of rule.'),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of media.'),
            (new StringField('formatted_handler_identifier', 'formattedHandlerIdentifier'))->addFlags(new WriteProtected(), new Runtime()),
            (new StringField('technical_name', 'technicalName'))->addFlags(new ApiAware(), new Required()),
            (new TranslationsAssociationField(PaymentMethodTranslationDefinition::class, 'payment_method_id'))->addFlags(new ApiAware(), new Required()),
            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Payment method logo or icon image'),
            new ManyToOneAssociationField('availabilityRule', 'availability_rule_id', RuleDefinition::class, 'id'),

            // Reverse Associations, not available in store-api
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'payment_method_id', 'id'))->addFlags(new RestrictDelete()),
            new ManyToOneAssociationField('plugin', 'plugin_id', PluginDefinition::class, 'id'),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'last_payment_method_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderTransactions', OrderTransactionDefinition::class, 'payment_method_id', 'id'))->addFlags(new RestrictDelete()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelPaymentMethodDefinition::class, 'payment_method_id', 'sales_channel_id'),
            (new OneToOneAssociationField('appPaymentMethod', 'id', 'payment_method_id', AppPaymentMethodDefinition::class, false))->addFlags(new CascadeDelete()),

            // runtime fields
            (new StringField('short_name', 'shortName'))->addFlags(new ApiAware(), new Runtime()),
        ]);
    }
}
