<?php declare(strict_types=1);

namespace Shopware\Application\Context\Definition;

use Shopware\Application\Context\Collection\ContextCartModifierTranslationBasicCollection;
use Shopware\Application\Context\Collection\ContextCartModifierTranslationDetailCollection;
use Shopware\Application\Context\Event\ContextCartModifierTranslation\ContextCartModifierTranslationDeletedEvent;
use Shopware\Application\Context\Event\ContextCartModifierTranslation\ContextCartModifierTranslationWrittenEvent;
use Shopware\Application\Context\Repository\ContextCartModifierTranslationRepository;
use Shopware\Application\Context\Struct\ContextCartModifierTranslationBasicStruct;
use Shopware\Application\Context\Struct\ContextCartModifierTranslationDetailStruct;
use Shopware\System\Language\LanguageDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;

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
