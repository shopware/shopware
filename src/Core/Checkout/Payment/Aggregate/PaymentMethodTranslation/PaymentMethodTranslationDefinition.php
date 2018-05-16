<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation;

use Shopware\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Application\Language\LanguageDefinition;
use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection\PaymentMethodTranslationDetailCollection;
use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Event\PaymentMethodTranslationDeletedEvent;
use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Event\PaymentMethodTranslationWrittenEvent;
use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationRepository;
use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Struct\PaymentMethodTranslationBasicStruct;
use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Struct\PaymentMethodTranslationDetailStruct;

class PaymentMethodTranslationDefinition extends EntityDefinition
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
        return 'payment_method_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(PaymentMethodDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', \Shopware\Application\Language\LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new LongTextField('additional_description', 'additionalDescription'))->setFlags(new Required()),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', \Shopware\Application\Language\LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return PaymentMethodTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return PaymentMethodTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return PaymentMethodTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return PaymentMethodTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return PaymentMethodTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return PaymentMethodTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return PaymentMethodTranslationDetailCollection::class;
    }
}
