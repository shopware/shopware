<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CurrencyCollection extends EntityCollection
{
    /**
     * @var CurrencyEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? CurrencyEntity
    {
        return parent::get($id);
    }

    public function current(): CurrencyEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CurrencyEntity::class;
    }
}
