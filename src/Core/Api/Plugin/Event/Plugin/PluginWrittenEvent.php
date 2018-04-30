<?php declare(strict_types=1);

namespace Shopware\Api\Plugin\Event\Plugin;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Plugin\Definition\PluginDefinition;

class PluginWrittenEvent extends WrittenEvent
{
    public const NAME = 'plugin.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return PluginDefinition::class;
    }
}
