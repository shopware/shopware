<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class TaxCollection extends EntityCollection
{
    /**
     * @var TaxEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? TaxEntity
    {
        return parent::get($id);
    }

    public function current(): TaxEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return TaxEntity::class;
    }
}
