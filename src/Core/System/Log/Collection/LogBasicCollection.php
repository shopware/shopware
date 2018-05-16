<?php declare(strict_types=1);

namespace Shopware\System\Log\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Log\Struct\LogBasicStruct;

class LogBasicCollection extends EntityCollection
{
    /**
     * @var LogBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? LogBasicStruct
    {
        return parent::get($id);
    }

    public function current(): LogBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return LogBasicStruct::class;
    }
}
