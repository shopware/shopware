<?php declare(strict_types=1);

namespace Shopware\Core\System\Log;

use Shopware\Core\Framework\ORM\EntityCollection;


class LogCollection extends EntityCollection
{
    /**
     * @var LogStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? LogStruct
    {
        return parent::get($id);
    }

    public function current(): LogStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return LogStruct::class;
    }
}
