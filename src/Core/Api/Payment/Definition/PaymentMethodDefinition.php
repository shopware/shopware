<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Definition;

use Shopware\Api\Application\Definition\ApplicationDefinition;
use Shopware\Api\Customer\Definition\CustomerDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Order\Definition\OrderDefinition;
use Shopware\Api\Order\Definition\OrderTransactionDefinition;
use Shopware\Api\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Api\Payment\Collection\PaymentMethodDetailCollection;
use Shopware\Api\Payment\Event\PaymentMethod\PaymentMethodDeletedEvent;
use Shopware\Api\Payment\Event\PaymentMethod\PaymentMethodWrittenEvent;
use Shopware\Api\Payment\Repository\PaymentMethodRepository;
use Shopware\Api\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Api\Payment\Struct\PaymentMethodDetailStruct;
use Shopware\Api\Plugin\Definition\PluginDefinition;
use Shopware\Api\Shop\Definition\ShopDefinition;

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
            (new OneToManyAssociationField('shops', ShopDefinition::class, 'payment_method_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
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
