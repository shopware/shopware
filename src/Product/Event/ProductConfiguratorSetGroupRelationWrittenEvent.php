<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductConfiguratorSetGroupRelationWrittenEvent extends WrittenEvent
{
    const NAME = 'product_configurator_set_group_relation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_configurator_set_group_relation';
    }
}
