<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class PaymentMethodTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'payment_method_translation';
    }

    public static function getCollectionClass(): string
    {
        return PaymentMethodTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return PaymentMethodTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return PaymentMethodDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('additional_description', 'additionalDescription'),
        ]);
    }
}
