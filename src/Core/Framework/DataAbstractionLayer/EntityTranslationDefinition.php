<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LanguageParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

abstract class EntityTranslationDefinition extends EntityDefinition
{
    abstract public static function getDefinitionClass(): string;

    public static function getParentDefinitionClass(): ?string
    {
        return static::getDefinitionClass();
    }

    public static function getFields(): FieldCollection
    {
        if (isset(static::$fields[static::class])) {
            return static::$fields[static::class];
        }

        $fields = parent::getFields();
        foreach (self::getBaseFields() as $field) {
            $fields->add($field);
        }

        static::$fields[static::class] = $fields;

        return static::$fields[static::class];
    }

    /**
     * @return Field[]
     */
    private static function getBaseFields(): array
    {
        /** @var string|EntityDefinition $translatedDefinition */
        $translatedDefinition = static::getDefinitionClass();
        $entityName = $translatedDefinition::getEntityName();

        $propertyBaseName = \explode('_', $entityName);
        $propertyBaseName = \array_map('ucfirst', $propertyBaseName);
        $propertyBaseName = \lcfirst(\implode($propertyBaseName));

        $baseFields = [
            (new FkField($entityName . '_id', $propertyBaseName . 'Id', $translatedDefinition))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new LanguageParentFkField($translatedDefinition),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField($propertyBaseName, $entityName . '_id', $translatedDefinition, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ];

        if ($translatedDefinition::isVersionAware()) {
            $baseFields[] = (new ReferenceVersionField($translatedDefinition))->setFlags(new PrimaryKey(), new Required());
        }

        return $baseFields;
    }
}
