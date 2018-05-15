<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductConfigurator\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorDefinition;

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
