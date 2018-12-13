<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version\Aggregate\VersionCommit;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\Version\VersionDefinition;

class VersionCommitDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'version_commit';
    }

    public static function isVersionAware(): bool
    {
        return false;
    }

    public static function getDeleteProtectionKey(): ?string
    {
        return 'entity.version_commit';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('version_id', 'versionId', VersionDefinition::class))->setFlags(new Required()),
            new IdField('user_id', 'userId'),
            new IdField('integration_id', 'integrationId'),
            (new IntField('auto_increment', 'autoIncrement'))->setFlags(new ReadOnly()),
            new BoolField('is_merge', 'isMerge'),
            (new StringField('message', 'message'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new CreatedAtField(),
            (new OneToManyAssociationField('data', VersionCommitDataDefinition::class, 'version_commit_id', true))->setFlags(new CascadeDelete()),
            new ManyToOneAssociationField('version', 'version_id', VersionDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return VersionCommitCollection::class;
    }

    public static function getStructClass(): string
    {
        return VersionCommitStruct::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [
            'name' => 'auto-save',
            'createdAt' => date(\DateTime::ATOM),
        ];
    }

    public static function getRootEntity(): ?string
    {
        return VersionDefinition::class;
    }
}
