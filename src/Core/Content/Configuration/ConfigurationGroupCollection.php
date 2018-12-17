<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ConfigurationGroupCollection extends EntityCollection
{
    /**
     * @var ConfigurationGroupEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigurationGroupEntity
    {
        return parent::get($id);
    }

    public function current(): ConfigurationGroupEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupEntity::class;
    }
}
