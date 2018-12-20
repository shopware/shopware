<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ConfigurationGroupCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ConfigurationGroupEntity::class;
    }
}
