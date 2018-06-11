<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Collection;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Currency\Struct\CurrencyBasicStruct;

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
