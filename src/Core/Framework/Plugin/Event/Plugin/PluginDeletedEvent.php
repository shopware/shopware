<?php declare(strict_types=1);

namespace Shopware\Framework\Plugin\Event\Plugin;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Framework\Plugin\Definition\PluginDefinition;

class PluginDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'plugin.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return PluginDefinition::class;
    }
}
