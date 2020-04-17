<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ImportExportProfileTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = ImportExportProfileDefinition::ENTITY_NAME . '_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ImportExportProfileTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return ImportExportProfileTranslationEntity::class;
    }

    public function getParentDefinitionClass(): string
    {
        return ImportExportProfileDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label')),
        ]);
    }
}
