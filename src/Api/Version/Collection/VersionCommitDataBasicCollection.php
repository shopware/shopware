<?php declare(strict_types=1);

namespace Shopware\Api\Version\Collection;

use Shopware\Api\Version\Struct\VersionCommitDataBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class VersionCommitDataBasicCollection extends EntityCollection
{
    /**
     * @var VersionCommitDataBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? VersionCommitDataBasicStruct
    {
        return parent::get($id);
    }

    public function current(): VersionCommitDataBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return VersionCommitDataBasicStruct::class;
    }
}
