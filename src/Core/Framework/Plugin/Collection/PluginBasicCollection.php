<?php declare(strict_types=1);

namespace Shopware\Framework\Plugin\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Framework\Plugin\Struct\PluginBasicStruct;

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
