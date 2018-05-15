<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductConfigurator;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductConfiguratorDefinition;

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
