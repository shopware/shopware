<?php declare(strict_types=1);

namespace Shopware\Core\System\Log;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Log\LogBasicStruct;

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
