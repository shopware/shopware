<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

class StateMachineTranslationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'state_machine_translation';
    }

    public static function getStructClass(): string
    {
        return StateMachineTranslationStruct::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),

            (new FkField('state_machine_id', 'stateMachineId', StateMachineDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('stateMachine', 'state_machine_id', StateMachineDefinition::class, false),

            (new StringField('name', 'name'))->setFlags(new Required()),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
