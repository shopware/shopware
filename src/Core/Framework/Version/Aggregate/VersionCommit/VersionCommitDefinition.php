<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version\Aggregate\VersionCommit;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
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

    public static function getCollectionClass(): string
    {
        return VersionCommitCollection::class;
    }

    public static function getEntityClass(): string
    {
        return VersionCommitEntity::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [
            'name' => 'auto-save',
            'createdAt' => date(Defaults::DATE_FORMAT),
        ];
    }

    public static function getParentDefinitionClass(): ?string
    {
        return VersionDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('version_id', 'versionId', VersionDefinition::class))->addFlags(new Required()),
            new IdField('user_id', 'userId'),
            new IdField('integration_id', 'integrationId'),
            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new WriteProtected()),
            new BoolField('is_merge', 'isMerge'),
            (new StringField('message', 'message'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new CreatedAtField(),
            (new OneToManyAssociationField('data', VersionCommitDataDefinition::class, 'version_commit_id'))->addFlags(new CascadeDelete()),
            new ManyToOneAssociationField('version', 'version_id', VersionDefinition::class, 'id', false),
        ]);
    }
}
