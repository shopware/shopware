<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version\Aggregate\VersionCommit;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitStruct;

class VersionCommitCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? VersionCommitStruct
    {
        return parent::get($id);
    }

    public function current(): VersionCommitStruct
    {
        return parent::current();
    }

    public function getUserIds(): array
    {
        return $this->fmap(function (VersionCommitStruct $versionChange) {
            return $versionChange->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (VersionCommitStruct $versionChange) use ($id) {
            return $versionChange->getUserId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return VersionCommitStruct::class;
    }
}
