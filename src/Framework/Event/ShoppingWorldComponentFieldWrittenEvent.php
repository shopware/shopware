<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class ShoppingWorldComponentFieldWrittenEvent extends WrittenEvent
{
    const NAME = 'shopping_world_component_field.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shopping_world_component_field';
    }
}
