<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class PluginCollection extends EntityCollection
{
    /**
     * @var PluginStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? PluginStruct
    {
        return parent::get($id);
    }

    public function current(): PluginStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return PluginStruct::class;
    }
}
