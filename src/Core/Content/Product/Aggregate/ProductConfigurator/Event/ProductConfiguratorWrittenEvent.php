<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductConfigurator\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorDefinition;

class ProductConfiguratorWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_configurator.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductConfiguratorDefinition::class;
    }
}
