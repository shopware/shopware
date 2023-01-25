<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Aggregate\FlowSequence;

use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class FlowSequenceDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'flow_sequence';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return FlowSequenceCollection::class;
    }

    public function getEntityClass(): string
    {
        return FlowSequenceEntity::class;
    }

    public function getDefaults(): array
    {
        return ['trueCase' => false, 'position' => 1];
    }

    public function since(): ?string
    {
        return '6.4.6.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return FlowDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('flow_id', 'flowId', FlowDefinition::class))->addFlags(new Required()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class)),
            (new StringField('action_name', 'actionName', 255))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new JsonField('config', 'config', [], [])),
            new IntField('position', 'position'),
            new IntField('display_group', 'displayGroup'),
            new BoolField('true_case', 'trueCase'),
            new ManyToOneAssociationField('flow', 'flow_id', FlowDefinition::class, 'id', false),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id', false),
            new ParentAssociationField(self::class, 'id'),
            new ChildrenAssociationField(self::class),
            new ParentFkField(self::class),
            new CustomFields(),
            new FkField('app_flow_action_id', 'appFlowActionId', AppFlowActionDefinition::class),
            new ManyToOneAssociationField('appFlowAction', 'app_flow_action_id', AppFlowActionDefinition::class, 'id', false),
        ]);
    }
}
