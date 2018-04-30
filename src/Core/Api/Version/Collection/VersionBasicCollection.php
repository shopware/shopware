<?php declare(strict_types=1);

namespace Shopware\Api\Version\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Version\Struct\VersionBasicStruct;

class VersionBasicCollection extends EntityCollection
{
    /**
     * @var VersionBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? VersionBasicStruct
    {
        return parent::get($id);
    }

    public function current(): VersionBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return VersionBasicStruct::class;
    }
}
