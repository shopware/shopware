<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class PluginCategoryWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'plugin_category.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'plugin_category';
    }
}
