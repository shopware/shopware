<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class StateMachineTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'state_machine_translation';
    }

    public static function getEntityClass(): string
    {
        return StateMachineTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return StateMachineDefinition::class;
    }

    public static function getCollectionClass(): string
    {
        return StateMachineTranslationCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new Required()),
            new CustomFields(),
        ]);
    }
}
