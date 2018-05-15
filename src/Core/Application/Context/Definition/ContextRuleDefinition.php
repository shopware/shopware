<?php declare(strict_types=1);

namespace Shopware\Application\Context\Definition;

use Shopware\Application\Context\Collection\ContextRuleBasicCollection;
use Shopware\Application\Context\Event\ContextRule\ContextRuleDeletedEvent;
use Shopware\Application\Context\Event\ContextRule\ContextRuleWrittenEvent;
use Shopware\Application\Context\Repository\ContextRuleRepository;
use Shopware\Application\Context\Struct\ContextRuleBasicStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\JsonObjectField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\Serialized;
use Shopware\Api\Entity\Write\Flag\WriteOnly;

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
