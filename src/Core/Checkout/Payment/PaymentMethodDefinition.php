<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class PaymentMethodDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'payment_method';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            new VersionField(),
            new FkField('plugin_id', 'pluginId', PluginDefinition::class),
            (new StringField('technical_name', 'technicalName'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('name'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new TranslatedField('additionalDescription'),
            new StringField('template', 'template'),
            new StringField('class', 'class'),
            new StringField('table', 'table'),
            new BoolField('hide', 'hide'),
            new FloatField('percentage_surcharge', 'percentageSurcharge'),
            new FloatField('absolute_surcharge', 'absoluteSurcharge'),
            new StringField('surcharge_string', 'surchargeString'),
            new IntField('position', 'position'),
            new BoolField('active', 'active'),
            new BoolField('allow_esd', 'allowEsd'),
            new StringField('used_iframe', 'usedIframe'),
            new BoolField('hide_prospect', 'hideProspect'),
            new StringField('action', 'action'),
            new IntField('source', 'source'),
            new BoolField('mobile_inactive', 'mobileInactive'),
            new LongTextField('risk_rules', 'riskRules'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            new ManyToOneAssociationField('plugin', 'plugin_id', PluginDefinition::class, false),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'default_payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'last_payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderTransactions', OrderTransactionDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new TranslationsAssociationField(PaymentMethodTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelPaymentMethodDefinition::class, false, 'payment_method_id', 'sales_channel_id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return PaymentMethodCollection::class;
    }

    public static function getStructClass(): string
    {
        return PaymentMethodStruct::class;
    }
}
