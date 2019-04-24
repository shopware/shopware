<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Checkout\Payment\SalesChannel\SalesChannelPaymentMethodDefinition as SalesChannelApiPaymentMethodDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Internal;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
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

    public static function getSalesChannelDecorationDefinition(): string
    {
        return SalesChannelApiPaymentMethodDefinition::class;
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
            (new StringField('handler_identifier', 'handlerIdentifier'))->addFlags(new Internal(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('description'),
            new IntField('position', 'position'),
            new BoolField('active', 'active'),
            new TranslatedField('attributes'),
            new FkField('availability_rule_id', 'availabilityRuleId', RuleDefinition::class),
            new FkField('media_id', 'mediaId', MediaDefinition::class),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new StringField('handler_identifier', 'formattedHandlerIdentifier'))->addFlags(new WriteProtected(), new Deferred()),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'payment_method_id', 'id'))->addFlags(new RestrictDelete()),
            new ManyToOneAssociationField('plugin', 'plugin_id', PluginDefinition::class, 'id', false),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'default_payment_method_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'last_payment_method_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderTransactions', OrderTransactionDefinition::class, 'payment_method_id', 'id'))->addFlags(new RestrictDelete()),
            (new TranslationsAssociationField(PaymentMethodTranslationDefinition::class, 'payment_method_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelPaymentMethodDefinition::class, 'payment_method_id', 'sales_channel_id'),
            new ManyToOneAssociationField('availabilityRule', 'availability_rule_id', RuleDefinition::class, 'id', false),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false),
        ]);
    }
}
