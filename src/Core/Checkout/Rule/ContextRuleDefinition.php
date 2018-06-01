<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Rule;

use Shopware\Core\Application\Context\Definition\ContextCartModifierDefinition;
use Shopware\Core\Checkout\Rule\Collection\ContextRuleBasicCollection;
use Shopware\Core\Checkout\Rule\Event\ContextRuleDeletedEvent;
use Shopware\Core\Checkout\Rule\Event\ContextRuleWrittenEvent;
use Shopware\Core\Checkout\Rule\Struct\ContextRuleBasicStruct;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\JsonObjectField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\Serialized;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;

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
