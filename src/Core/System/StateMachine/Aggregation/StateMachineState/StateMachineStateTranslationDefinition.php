<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class StateMachineStateTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'state_machine_state_translation';
    }

    public static function getEntityClass(): string
    {
        return StateMachineStateTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return StateMachineStateDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new Required()),
        ]);
    }
}
