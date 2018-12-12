<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version\Aggregate\VersionCommit;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class VersionCommitCollection extends EntityCollection
{
    /**
     * @var VersionCommitEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? VersionCommitEntity
    {
        return parent::get($id);
    }

    public function current(): VersionCommitEntity
    {
        return parent::current();
    }

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
