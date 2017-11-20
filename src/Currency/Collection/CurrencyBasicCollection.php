<?php declare(strict_types=1);

namespace Shopware\Currency\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Currency\Struct\CurrencyBasicStruct;

class CurrencyBasicCollection extends EntityCollection
{
    /**
     * @var CurrencyBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CurrencyBasicStruct
    {
        return parent::get($uuid);
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
