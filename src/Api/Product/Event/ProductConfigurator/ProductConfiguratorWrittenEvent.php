<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductConfigurator;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductConfiguratorDefinition;

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
