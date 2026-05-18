<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Run;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportV2RunDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'import_export_v2_run';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ImportExportV2RunCollection::class;
    }

    public function getEntityClass(): string
    {
        return ImportExportV2RunEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('type', 'type'))->addFlags(new Required()),
            (new StringField('profile_name', 'profileName'))->addFlags(new Required()),
            (new StringField('state', 'state'))->addFlags(new Required()),
            (new IntField('processed', 'processed'))->addFlags(new Required()),
            (new IntField('succeeded', 'succeeded'))->addFlags(new Required()),
            (new IntField('failed', 'failed'))->addFlags(new Required()),
            (new IntField('offset', 'offset'))->addFlags(new Required()),
            (new IntField('limit', 'limit'))->addFlags(new Required()),
            new IntField('next_byte_offset', 'nextByteOffset'),
            new IntField('total_records', 'totalRecords'),
            (new JsonField('export_filters', 'exportFilters'))->addFlags(new Required()),
            (new StringField('file_id', 'fileId'))->addFlags(new Required()),
            new StringField('invalid_records_file_id', 'invalidRecordsFileId'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
