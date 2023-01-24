<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<VersionCommitEntity>
 *
 * @package core
 */
class VersionCommitCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getUserIds(): array
    {
        return $this->fmap(fn (VersionCommitEntity $versionChange) => $versionChange->getUserId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(fn (VersionCommitEntity $versionChange) => $versionChange->getUserId() === $id);
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
