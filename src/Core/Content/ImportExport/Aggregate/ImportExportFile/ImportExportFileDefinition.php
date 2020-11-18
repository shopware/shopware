<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ImportExportFileDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'import_export_file';

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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('original_name', 'originalName'))->setFlags(new Required()),
            (new StringField('path', 'path'))->setFlags(new Required()),
            (new DateTimeField('expire_date', 'expireDate'))->setFlags(new Required()),
            new IntField('size', 'size'),
            new OneToOneAssociationField('log', 'id', 'file_id', ImportExportLogDefinition::class, false),
            new CreatedAtField(),
            (new StringField('access_token', 'accessToken'))->setFlags(new Required()),
        ]);
    }
}
