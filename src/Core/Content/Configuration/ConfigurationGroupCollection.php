<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ConfigurationGroupCollection extends EntityCollection
{
    /**
     * @var ConfigurationGroupStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigurationGroupStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigurationGroupStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupStruct::class;
    }
}
