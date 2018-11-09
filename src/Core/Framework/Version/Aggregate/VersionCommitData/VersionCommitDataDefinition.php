<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version\Aggregate\VersionCommitData;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitDefinition;

class VersionCommitDataDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'version_commit_data';
    }

    public static function isVersionAware(): bool
    {
        return false;
    }

    public static function getDeleteProtectionKey(): ?string
    {
        return 'entity.version_commit_data';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('version_commit_id', 'versionCommitId', VersionCommitDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('commit', 'version_commit_id', VersionCommitDefinition::class, false),
            new IdField('user_id', 'userId'),
            new IdField('integration_id', 'integrationId'),
            new IntField('auto_increment', 'autoIncrement'),
            (new StringField('entity_name', 'entityName'))->setFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new JsonField('entity_id', 'entityId'))->setFlags(new Required()),
            (new StringField('action', 'action'))->setFlags(new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new VersionDataPayloadField('payload', 'payload'))->setFlags(new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            new CreatedAtField(),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return VersionCommitDataCollection::class;
    }

    public static function getEntityClass(): string
    {
        return VersionCommitDataEntity::class;
    }

    public static function getRootEntity(): ?string
    {
        return VersionCommitDataDefinition::class;
    }
}
