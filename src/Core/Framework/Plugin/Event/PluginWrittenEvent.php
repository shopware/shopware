<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\Framework\Plugin\PluginDefinition;

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
