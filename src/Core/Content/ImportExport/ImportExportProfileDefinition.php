<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportProfileDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'import_export_profile';

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
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of import-export profile.'),
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('label'))->addFlags(new Required()),
            (new StringField('type', 'type'))->setDescription('Import-export type can be orders, customers, categories.'),
            (new BoolField('system_default', 'systemDefault'))->setDescription('When boolean value is true `true`, then its a system default profile.'),
            (new StringField('source_entity', 'sourceEntity'))->addFlags(new Required()),
            (new StringField('file_type', 'fileType'))->addFlags(new Required())->setDescription('Type of file like PDF.'),
            (new StringField('delimiter', 'delimiter'))->addFlags(new Required())->setDescription('Characters used as the delimiter for the specific profile, aiding in proper data parsing during import-export operations.'),
            (new StringField('enclosure', 'enclosure'))->addFlags(new Required())->setDescription('Specifies the enclosure character used to wrap or enclose data fields, especially when those fields contain special characters or delimiters.'),
            (new JsonField('mapping', 'mapping', [], []))->setDescription('Defines the connection to the different database columns.'),
            new JsonField('update_by', 'updateBy', [], []),
            (new JsonField('config', 'config', [], []))->setDescription('Specifies detailed information about the component.'),
            (new OneToManyAssociationField('importExportLogs', ImportExportLogDefinition::class, 'profile_id'))->addFlags(new SetNullOnDelete()),
            (new TranslationsAssociationField(ImportExportProfileTranslationDefinition::class, 'import_export_profile_id'))->addFlags(new Required()),
        ]);
    }
}
