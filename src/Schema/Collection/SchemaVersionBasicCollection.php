<?php declare(strict_types=1);

namespace Shopware\Schema\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Schema\Struct\SchemaVersionBasicStruct;

class SchemaVersionBasicCollection extends EntityCollection
{
    /**
     * @var SchemaVersionBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? SchemaVersionBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): SchemaVersionBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return SchemaVersionBasicStruct::class;
    }
}
