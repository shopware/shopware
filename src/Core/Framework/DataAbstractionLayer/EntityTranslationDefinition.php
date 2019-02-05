<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

abstract class EntityTranslationDefinition extends EntityDefinition
{
    /**
     * @return string|EntityDefinition
     */
    public static function getParentDefinitionClass(): string
    {
        throw new \RuntimeException('`getParentDefinitionClass` not implemented');
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

    public static function isVersionAware(): bool
    {
        return static::getParentDefinitionClass()::isVersionAware();
    }

    public static function hasRequiredField(): bool
    {
        return static::getFields()
                ->filterByFlag(Required::class)
                ->filter(function (Field $field) {
                    return !(
                        $field instanceof FkField ||
                        $field instanceof CreatedAtField ||
                        $field instanceof UpdatedAtField
                    );
                })
                ->count()
            > 0;
    }

    /**
     * @return Field[]
     */
    private static function getBaseFields(): array
    {
        /** @var string|EntityDefinition $translatedDefinition */
        $translatedDefinition = static::getParentDefinitionClass();
        $entityName = $translatedDefinition::getEntityName();

        $propertyBaseName = \explode('_', $entityName);
        $propertyBaseName = \array_map('ucfirst', $propertyBaseName);
        $propertyBaseName = \lcfirst(\implode($propertyBaseName));

        $baseFields = [
            (new FkField($entityName . '_id', $propertyBaseName . 'Id', $translatedDefinition))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField($propertyBaseName, $entityName . '_id', $translatedDefinition, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ];

        if (static::isVersionAware()) {
            $baseFields[] = (new ReferenceVersionField($translatedDefinition))->setFlags(new PrimaryKey(), new Required());
        }

        return $baseFields;
    }
}
