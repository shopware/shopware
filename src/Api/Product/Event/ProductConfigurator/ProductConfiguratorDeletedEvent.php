<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductConfigurator;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductConfiguratorDefinition;

class ProductConfiguratorDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_configurator.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductConfiguratorDefinition::class;
    }
}
