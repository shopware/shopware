<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;

class ImportExportProfileDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'import_export_profile';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ImportExportProfileEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('name', 'name')),
            (new TranslatedField('label'))->addFlags(new Required()),
            new BoolField('system_default', 'systemDefault'),
            (new StringField('source_entity', 'sourceEntity'))->addFlags(new Required()),
            (new StringField('file_type', 'fileType'))->addFlags(new Required()),
            (new StringField('delimiter', 'delimiter'))->addFlags(new Required()),
            (new StringField('enclosure', 'enclosure'))->addFlags(new Required()),
            (new JsonField('mapping', 'mapping', [], [])),
            (new JsonField('config', 'config', [], [])),
            (new OneToManyAssociationField('importExportLogs', ImportExportLogDefinition::class, 'profile_id'))->addFlags(new SetNullOnDelete()),
            (new TranslationsAssociationField(ImportExportProfileTranslationDefinition::class, 'import_export_profile_id'))->addFlags(new Required()),
        ]);

        if (Feature::isActive('FEATURE_NEXT_8097')) {
            $fields->add(new StringField('type', 'type'));
        }

        return $fields;
    }
}
