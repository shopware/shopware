<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Version;

use Shopware\Core\Framework\Struct\Collection;

class ObjectCollection extends Collection
{
    protected $elements = [];

    public function add($item)
    {
        $this->elements[] = $item;
    }
}
