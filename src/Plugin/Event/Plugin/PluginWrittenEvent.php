<?php declare(strict_types=1);

namespace Shopware\Plugin\Event\Plugin;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Plugin\Definition\PluginDefinition;

class PluginWrittenEvent extends WrittenEvent
{
    const NAME = 'plugin.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return PluginDefinition::class;
    }
}
