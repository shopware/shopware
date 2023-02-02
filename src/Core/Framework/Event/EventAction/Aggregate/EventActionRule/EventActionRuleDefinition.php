<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventAction\Aggregate\EventActionRule;

use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Event\EventAction\EventActionDefinition;

/**
 * @deprecated tag:v6.5.0 - reason:remove-entity - Will be removed in v6.5.0.
 */
class EventActionRuleDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'event_action_rule';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.3.3.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('event_action_id', 'eventActionId', EventActionDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('eventAction', 'event_action_id', EventActionDefinition::class, 'id', false),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id', false),
        ]);
    }
}
