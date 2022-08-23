<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<VersionCommitEntity>
 */
class VersionCommitCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getUserIds(): array
    {
        return $this->fmap(function (VersionCommitEntity $versionChange) {
            return $versionChange->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (VersionCommitEntity $versionChange) use ($id) {
            return $versionChange->getUserId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'dal_version_commit_collection';
    }

    protected function getExpectedClass(): string
    {
        return VersionCommitEntity::class;
    }
}
