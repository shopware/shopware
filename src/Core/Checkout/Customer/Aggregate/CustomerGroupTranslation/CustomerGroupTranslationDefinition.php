<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class CustomerGroupTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'customer_group_translation';
    }

    public static function getCollectionClass(): string
    {
        return CustomerGroupTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CustomerGroupTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return CustomerGroupDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new AttributesField(),
        ]);
    }
}
