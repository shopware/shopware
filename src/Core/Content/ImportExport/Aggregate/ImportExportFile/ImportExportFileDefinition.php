<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportFileDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'import_export_file';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ImportExportFileEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of import-export file.'),
            (new StringField('original_name', 'originalName'))->addFlags(new Required())->setDescription('Original name of the import-export file.'),
            (new StringField('path', 'path'))->addFlags(new Required())->setDescription('A relative URL to the import-export file.'),
            (new DateTimeField('expire_date', 'expireDate'))->addFlags(new Required())->setDescription('Date and time of import-export file expiry.'),
            (new IntField('size', 'size'))->setDescription('Size of the import-export file.'),
            (new OneToOneAssociationField('log', 'id', 'file_id', ImportExportLogDefinition::class, false))->addFlags(new CascadeDelete()),
            (new StringField('access_token', 'accessToken'))->setDescription('Secret key to access import-export file.'),
        ]);
    }
}
