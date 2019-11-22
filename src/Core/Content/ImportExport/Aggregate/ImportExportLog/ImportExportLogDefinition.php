<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileDefinition;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

class ImportExportLogDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'import_export_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ImportExportLogEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('activity', 'activity'))->setFlags(new Required()),
            (new StringField('state', 'state'))->setFlags(new Required()),
            (new IntField('records', 'records'))->setFlags(new Required()),
            (new FkField('user_id', 'userId', UserDefinition::class)),
            (new FkField('profile_id', 'profileId', ImportExportProfileDefinition::class)),
            (new FkField('file_id', 'fileId', ImportExportFileDefinition::class)),
            (new StringField('username', 'username')),
            (new StringField('profile_name', 'profileName')),
            (new ManyToOneAssociationField('user', 'user_id', UserDefinition::class))
                ->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new ManyToOneAssociationField('profile', 'profile_id', ImportExportProfileDefinition::class, 'id', true)),
            (new OneToOneAssociationField('file', 'file_id', 'id', ImportExportFileDefinition::class, true)),
            (new CreatedAtField()),
            (new UpdatedAtField()),
        ]);
    }
}
