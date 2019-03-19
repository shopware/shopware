<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodRules\PaymentMethodRuleDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class PaymentMethodDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'payment_method';
    }

    public static function getCollectionClass(): string
    {
        return PaymentMethodCollection::class;
    }

    public static function getEntityClass(): string
    {
        return PaymentMethodEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            new FkField('plugin_id', 'pluginId', PluginDefinition::class),
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('additionalDescription'),
            new StringField('template', 'template'),
            new StringField('class', 'class'),
            new FloatField('percentage_surcharge', 'percentageSurcharge'),
            new FloatField('absolute_surcharge', 'absoluteSurcharge'),
            new StringField('surcharge_string', 'surchargeString'),
            new IntField('position', 'position'),
            new BoolField('active', 'active'),
            new TranslatedField('attributes'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'payment_method_id', false, 'id'))->addFlags(new RestrictDelete()),
            new ManyToOneAssociationField('plugin', 'plugin_id', PluginDefinition::class, false),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'default_payment_method_id', false, 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'last_payment_method_id', false, 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderTransactions', OrderTransactionDefinition::class, 'payment_method_id', false, 'id'))->addFlags(new RestrictDelete()),
            (new TranslationsAssociationField(PaymentMethodTranslationDefinition::class, 'payment_method_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelPaymentMethodDefinition::class, false, 'payment_method_id', 'sales_channel_id'),
            (new ManyToManyAssociationField('availabilityRules', RuleDefinition::class, PaymentMethodRuleDefinition::class, false, 'payment_method_id', 'rule_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
