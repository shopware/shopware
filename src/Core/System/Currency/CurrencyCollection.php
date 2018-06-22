<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\ORM\EntityCollection;

class CurrencyCollection extends EntityCollection
{
    /**
     * @var CurrencyStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CurrencyStruct
    {
        return parent::get($id);
    }

    public function current(): CurrencyStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CurrencyStruct::class;
    }
}
