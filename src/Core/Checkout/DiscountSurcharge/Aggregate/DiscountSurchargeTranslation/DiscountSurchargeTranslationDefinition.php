<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class DiscountSurchargeTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'discount_surcharge_translation';
    }

    public static function getCollectionClass(): string
    {
        return DiscountSurchargeTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return DiscountSurchargeTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return DiscountSurchargeDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
        ]);
    }
}
