<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class ImportExportProfileTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = ImportExportProfileDefinition::ENTITY_NAME . '_translation';

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

    public function since(): ?string
    {
        return '6.2.0.0';
    }

    protected function getParentDefinitionClass(): string
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
