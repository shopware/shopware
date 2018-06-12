<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Tax\TaxBasicStruct;

class TaxBasicCollection extends EntityCollection
{
    /**
     * @var TaxBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? TaxBasicStruct
    {
        return parent::get($id);
    }

    public function current(): TaxBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return TaxBasicStruct::class;
    }
}
