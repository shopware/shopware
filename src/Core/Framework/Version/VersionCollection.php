<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\Version\VersionStruct;

class VersionCollection extends EntityCollection
{
    /**
     * @var VersionStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? VersionStruct
    {
        return parent::get($id);
    }

    public function current(): VersionStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return VersionStruct::class;
    }
}
