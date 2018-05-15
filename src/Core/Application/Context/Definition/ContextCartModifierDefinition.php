<?php declare(strict_types=1);

namespace Shopware\Application\Context\Definition;

use Shopware\Application\Context\Collection\ContextCartModifierBasicCollection;
use Shopware\Application\Context\Collection\ContextCartModifierDetailCollection;
use Shopware\Application\Context\Event\ContextCartModifier\ContextCartModifierDeletedEvent;
use Shopware\Application\Context\Event\ContextCartModifier\ContextCartModifierWrittenEvent;
use Shopware\Application\Context\Repository\ContextCartModifierRepository;
use Shopware\Application\Context\Struct\ContextCartModifierBasicStruct;
use Shopware\Application\Context\Struct\ContextCartModifierDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\JsonObjectField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\Serialized;

class ContextCartModifierDefinition extends EntityDefinition
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
        return 'context_cart_modifier';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            (new FkField('context_rule_id', 'contextRuleId', ContextRuleDefinition::class))->setFlags(new Required()),
            (new JsonObjectField('rule', 'rule'))->setFlags(new Serialized(), new Required()),
            (new StringField('type', 'type'))->setFlags(new Required()),
            (new FloatField('amount', 'amount'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),

            (new TranslationsAssociationField('translations', ContextCartModifierTranslationDefinition::class, 'context_cart_modifier_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            new ManyToOneAssociationField('contextRule', 'context_rule_id', ContextRuleDefinition::class, true),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ContextCartModifierRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ContextCartModifierBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ContextCartModifierDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ContextCartModifierWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ContextCartModifierBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ContextCartModifierTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return ContextCartModifierDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ContextCartModifierDetailCollection::class;
    }
}
