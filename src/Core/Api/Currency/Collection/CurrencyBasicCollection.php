<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Collection;

use Shopware\Api\Currency\Struct\CurrencyBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class CurrencyBasicCollection extends EntityCollection
{
    /**
     * @var CurrencyBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CurrencyBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CurrencyBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CurrencyBasicStruct::class;
    }
}
