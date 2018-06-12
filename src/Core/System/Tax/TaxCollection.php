<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Framework\ORM\EntityCollection;

class TaxCollection extends EntityCollection
{
    /**
     * @var TaxStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? TaxStruct
    {
        return parent::get($id);
    }

    public function current(): TaxStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return TaxStruct::class;
    }
}
