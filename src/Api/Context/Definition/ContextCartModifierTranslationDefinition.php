<?php declare(strict_types=1);

namespace Shopware\Api\Context\Definition;

use Shopware\Api\Context\Collection\ContextCartModifierTranslationBasicCollection;
use Shopware\Api\Context\Collection\ContextCartModifierTranslationDetailCollection;
use Shopware\Api\Context\Event\ContextCartModifierTranslation\ContextCartModifierTranslationDeletedEvent;
use Shopware\Api\Context\Event\ContextCartModifierTranslation\ContextCartModifierTranslationWrittenEvent;
use Shopware\Api\Context\Repository\ContextCartModifierTranslationRepository;
use Shopware\Api\Context\Struct\ContextCartModifierTranslationBasicStruct;
use Shopware\Api\Context\Struct\ContextCartModifierTranslationDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Language\Definition\LanguageDefinition;

class ContextCartModifierTranslationDefinition extends EntityDefinition
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
        return 'context_cart_modifier_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('context_cart_modifier_id', 'contextCartModifierId', ContextCartModifierDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('contextCartModifier', 'context_cart_modifier_id', ContextCartModifierDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ContextCartModifierTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ContextCartModifierTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ContextCartModifierTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ContextCartModifierTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ContextCartModifierTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ContextCartModifierTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ContextCartModifierTranslationDetailCollection::class;
    }
}
