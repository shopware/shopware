<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\FloatField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\System\Touchpoint\TouchpointDefinition;

class PaymentMethodDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'payment_method';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new FkField('plugin_id', 'pluginId', PluginDefinition::class),
            (new StringField('technical_name', 'technicalName'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new TranslatedField(new LongTextField('additional_description', 'additionalDescription')),
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
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new OneToManyAssociationField('touchpoints', TouchpointDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            new ManyToOneAssociationField('plugin', 'plugin_id', PluginDefinition::class, false),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'default_payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'last_payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('transactions', OrderTransactionDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new TranslationsAssociationField('translations', PaymentMethodTranslationDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
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

    public static function getTranslationDefinitionClass(): ?string
    {
        return PaymentMethodTranslationDefinition::class;
    }
}
