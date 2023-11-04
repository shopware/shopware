<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Aggregate\RuleCondition;

use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
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
class RuleConditionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'rule_condition';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return RuleConditionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return RuleConditionCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return RuleDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('type', 'type'))->addFlags(new Required()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new Required()),
            new FkField('script_id', 'scriptId', AppScriptConditionDefinition::class),
            new ParentFkField(self::class),
            new JsonField('value', 'value'),
            new IntField('position', 'position'),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id', false),
            new ManyToOneAssociationField('appScriptCondition', 'script_id', AppScriptConditionDefinition::class, 'id', true),
            new ParentAssociationField(self::class, 'id'),
            new ChildrenAssociationField(self::class),
            new CustomFields(),
        ]);
    }
}
