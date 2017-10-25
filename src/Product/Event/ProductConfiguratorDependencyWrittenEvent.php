<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ProductConfiguratorDependencyWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'product_configurator_dependency.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_configurator_dependency';
    }
}
