<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileDefinition;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserDefinition;

#[Package('system-settings')]
class ImportExportLogDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'import_export_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ImportExportLogEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new WriteProtection(Context::SYSTEM_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        // @deprecated tag:v6.6.0 - Variable $autoload will be removed in the next major as it will be false by default
        $autoload = !Feature::isActive('v6.6.0.0');

        $fields = [
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('activity', 'activity'))->addFlags(new Required()),
            (new StringField('state', 'state'))->addFlags(new Required()),
            (new IntField('records', 'records'))->addFlags(new Required()),
            new FkField('user_id', 'userId', UserDefinition::class),
            new FkField('profile_id', 'profileId', ImportExportProfileDefinition::class),
            new FkField('file_id', 'fileId', ImportExportFileDefinition::class),
            new FkField('invalid_records_log_id', 'invalidRecordsLogId', ImportExportLogDefinition::class),
            new StringField('username', 'username'),
            new StringField('profile_name', 'profileName'),
            (new JsonField('config', 'config', [], []))->addFlags(new Required()),
            (new JsonField('result', 'result', [], [])),
            (new ManyToOneAssociationField('user', 'user_id', UserDefinition::class)),
            new ManyToOneAssociationField('profile', 'profile_id', ImportExportProfileDefinition::class, 'id'),
            new OneToOneAssociationField('file', 'file_id', 'id', ImportExportFileDefinition::class, $autoload),
            new OneToOneAssociationField('invalidRecordsLog', 'invalid_records_log_id', 'id', ImportExportLogDefinition::class, false),
            new OneToOneAssociationField('failedImportLog', 'id', 'invalid_records_log_id', ImportExportLogDefinition::class),
        ];

        return new FieldCollection($fields);
    }
}
