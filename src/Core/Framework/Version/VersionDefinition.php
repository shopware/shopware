<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitDefinition;

class VersionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'version';
    }

    public static function isVersionAware(): bool
    {
        return false;
    }

    public static function getCollectionClass(): string
    {
        return VersionCollection::class;
    }

    public static function getEntityClass(): string
    {
        return VersionEntity::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [
            'name' => sprintf('Draft (%s)', date(Defaults::DATE_FORMAT)),
            'createdAt' => date(Defaults::DATE_FORMAT),
        ];
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new CreatedAtField(),
            new UpdatedAtField(),
            new OneToManyAssociationField('commits', VersionCommitDefinition::class, 'version_id', true),
        ]);
    }
}
