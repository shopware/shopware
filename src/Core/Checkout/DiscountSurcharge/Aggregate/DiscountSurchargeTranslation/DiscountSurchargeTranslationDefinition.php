<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation;

use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Collection\DiscountSurchargeTranslationBasicCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Collection\DiscountSurchargeTranslationDetailCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Event\DiscountSurchargeTranslationDeletedEvent;
use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Event\DiscountSurchargeTranslationWrittenEvent;
use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeDefinition;
use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeTranslationBasicStruct;
use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeTranslationDetailStruct;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

class DiscountSurchargeTranslationDefinition extends EntityDefinition
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
        return 'discount_surcharge_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('discount_surcharge_id', 'discountSurchargeId', DiscountSurchargeDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('discountSurcharge', 'discount_surcharge_id', DiscountSurchargeDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return DiscountSurchargeTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return DiscountSurchargeTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return DiscountSurchargeTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return DiscountSurchargeTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return DiscountSurchargeTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return DiscountSurchargeTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return DiscountSurchargeTranslationDetailCollection::class;
    }
}
