<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class VersionCommitDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'version_commit';

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
        return VersionCommitCollection::class;
    }

    public function getEntityClass(): string
    {
        return VersionCommitEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'name' => 'auto-save',
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return VersionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('version_id', 'versionId', VersionDefinition::class))->addFlags(new Required()),
            new IdField('user_id', 'userId'),
            new IdField('integration_id', 'integrationId'),
            new AutoIncrementField(),
            new BoolField('is_merge', 'isMerge'),
            (new StringField('message', 'message'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new OneToManyAssociationField('data', VersionCommitDataDefinition::class, 'version_commit_id'))->addFlags(new CascadeDelete()),
            new ManyToOneAssociationField('version', 'version_id', VersionDefinition::class, 'id', false),
        ]);
    }
}
