<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;

use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

class DiscountSurchargeTranslationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'discount_surcharge_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('discount_surcharge_id', 'discountSurchargeId', DiscountSurchargeDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('discountSurcharge', 'discount_surcharge_id', DiscountSurchargeDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return DiscountSurchargeTranslationCollection::class;
    }

    public static function getStructClass(): string
    {
        return DiscountSurchargeTranslationStruct::class;
    }
}
