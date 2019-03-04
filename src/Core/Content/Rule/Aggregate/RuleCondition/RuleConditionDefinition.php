<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Aggregate\RuleCondition;

use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
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

class RuleConditionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'rule_condition';
    }

    public static function getEntityClass(): string
    {
        return RuleConditionEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return RuleConditionCollection::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return RuleDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('type', 'type'))->addFlags(new Required()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new Required()),
            new ParentFkField(self::class),
            new JsonField('value', 'value'),
            new IntField('position', 'position'),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, false, 'id'),
            new ParentAssociationField(self::class, false),
            new ChildrenAssociationField(self::class),
            new AttributesField(),
        ]);
    }
}
