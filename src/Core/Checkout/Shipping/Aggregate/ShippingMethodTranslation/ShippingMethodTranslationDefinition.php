<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class ShippingMethodTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'shipping_method_translation';
    }

    public static function getParentDefinitionClass(): string
    {
        return ShippingMethodDefinition::class;
    }

    public static function getCollectionClass(): string
    {
        return ShippingMethodTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ShippingMethodTranslationEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new StringField('comment', 'comment'),
        ]);
    }
}
