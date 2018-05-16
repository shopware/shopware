<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule;

use Shopware\Checkout\Rule\Collection\ContextRuleBasicCollection;
use Shopware\Application\Context\Definition\ContextCartModifierDefinition;
use Shopware\Checkout\Rule\Event\ContextRuleDeletedEvent;
use Shopware\Checkout\Rule\Event\ContextRuleWrittenEvent;

use Shopware\Checkout\Rule\Struct\ContextRuleBasicStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\JsonObjectField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\Serialized;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;

class ContextRuleDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'context_rule';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new IntField('priority', 'priority'))->setFlags(new Required()),
            (new JsonObjectField('payload', 'payload'))->setFlags(new Required(), new Serialized()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),

            (new OneToManyAssociationField('contextCartModifers', ContextCartModifierDefinition::class, 'context_rule_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ContextRuleRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ContextRuleBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ContextRuleDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ContextRuleWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ContextRuleBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
