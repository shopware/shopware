<?php declare(strict_types=1);

namespace Shopware\System\Currency\Collection;

use Shopware\System\Currency\Struct\CurrencyBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

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
