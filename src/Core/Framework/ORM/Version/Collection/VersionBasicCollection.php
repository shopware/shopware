<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Collection;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\Version\Struct\VersionBasicStruct;

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
