<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;

class StateMachineDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'state_machine';
    }

    public static function getEntityClass(): string
    {
        return StateMachineEntity::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return StateMachineTranslationDefinition::class;
    }

    public static function getCollectionClass(): string
    {
        return StateMachineCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new StringField('technical_name', 'technicalName'))->setFlags(new Required()),
            (new TranslatedField('name'))->setFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            new OneToManyAssociationField('states', StateMachineStateDefinition::class, 'state_machine_id', false),
            new OneToManyAssociationField('transitions', StateMachineTransitionDefinition::class, 'state_machine_id', false),

            new FkField('initial_state_id', 'initialStateId', StateMachineStateDefinition::class),

            (new TranslationsAssociationField(StateMachineTranslationDefinition::class, 'state_machine_id'))->setFlags(new CascadeDelete(), new Required()),

            new CreatedAtField(),
            new UpdatedAtField(),

            new OneToManyAssociationField('historyEntries', StateMachineHistoryDefinition::class, 'state_machine_id', false),
        ]);
    }
}
