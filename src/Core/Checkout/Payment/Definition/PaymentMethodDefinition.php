<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Definition;

use Shopware\Application\Application\ApplicationDefinition;
use Shopware\Checkout\Customer\CustomerDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\FloatField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Checkout\Order\OrderDefinition;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Checkout\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Checkout\Payment\Collection\PaymentMethodDetailCollection;
use Shopware\Checkout\Payment\Event\PaymentMethod\PaymentMethodDeletedEvent;
use Shopware\Checkout\Payment\Event\PaymentMethod\PaymentMethodWrittenEvent;
use Shopware\Checkout\Payment\Repository\PaymentMethodRepository;
use Shopware\Checkout\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Checkout\Payment\Struct\PaymentMethodDetailStruct;
use Shopware\Framework\Plugin\Definition\PluginDefinition;

class PaymentMethodDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'payment_method';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
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
            (new OneToManyAssociationField('applications', ApplicationDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            new ManyToOneAssociationField('plugin', 'plugin_id', PluginDefinition::class, false),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'default_payment_method_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'last_payment_method_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('transactions', OrderTransactionDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new TranslationsAssociationField('translations', PaymentMethodTranslationDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return PaymentMethodRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return PaymentMethodBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return PaymentMethodDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return PaymentMethodWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return PaymentMethodBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return PaymentMethodTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return PaymentMethodDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return PaymentMethodDetailCollection::class;
    }
}
