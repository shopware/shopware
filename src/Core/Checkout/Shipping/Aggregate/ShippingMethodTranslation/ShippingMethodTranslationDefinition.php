<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Collection\ShippingMethodTranslationBasicCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Collection\ShippingMethodTranslationDetailCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Event\ShippingMethodTranslationDeletedEvent;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Event\ShippingMethodTranslationWrittenEvent;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Struct\ShippingMethodTranslationBasicStruct;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Struct\ShippingMethodTranslationDetailStruct;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

class ShippingMethodTranslationDefinition extends EntityDefinition
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
        return 'shipping_method_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ShippingMethodDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new LongTextField('description', 'description'),
            new StringField('comment', 'comment'),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ShippingMethodTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ShippingMethodTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ShippingMethodTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ShippingMethodTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ShippingMethodTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ShippingMethodTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ShippingMethodTranslationDetailCollection::class;
    }
}
