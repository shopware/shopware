<?php declare(strict_types=1);

namespace Shopware\Api\Plugin\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Plugin\Struct\PluginBasicStruct;

class PluginBasicCollection extends EntityCollection
{
    /**
     * @var PluginBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? PluginBasicStruct
    {
        return parent::get($id);
    }

    public function current(): PluginBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return PluginBasicStruct::class;
    }
}
