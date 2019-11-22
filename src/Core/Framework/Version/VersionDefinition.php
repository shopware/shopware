<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitDefinition;

class VersionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'version';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isVersionAware(): bool
    {
        return false;
    }

    public function getCollectionClass(): string
    {
        return VersionCollection::class;
    }

    public function getEntityClass(): string
    {
        return VersionEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'name' => sprintf('Draft %s', date(Defaults::STORAGE_DATE_TIME_FORMAT)),
            'createdAt' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new OneToManyAssociationField('commits', VersionCommitDefinition::class, 'version_id'),
        ]);
    }
}
