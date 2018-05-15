<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Framework\ORM\Version\Struct\VersionCommitBasicStruct;

class VersionCommitBasicCollection extends EntityCollection
{
    /**
     * @var VersionCommitBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? VersionCommitBasicStruct
    {
        return parent::get($id);
    }

    public function current(): VersionCommitBasicStruct
    {
        return parent::current();
    }

    public function getUserIds(): array
    {
        return $this->fmap(function (VersionCommitBasicStruct $versionChange) {
            return $versionChange->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (VersionCommitBasicStruct $versionChange) use ($id) {
            return $versionChange->getUserId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return VersionCommitBasicStruct::class;
    }
}
