<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(VersionCommitEntity $entity)
 * @method void                     set(string $key, VersionCommitEntity $entity)
 * @method VersionCommitEntity[]    getIterator()
 * @method VersionCommitEntity[]    getElements()
 * @method VersionCommitEntity|null get(string $key)
 * @method VersionCommitEntity|null first()
 * @method VersionCommitEntity|null last()
 */
class VersionCommitCollection extends EntityCollection
{
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

    protected function getExpectedClass(): string
    {
        return VersionCommitEntity::class;
    }
}
