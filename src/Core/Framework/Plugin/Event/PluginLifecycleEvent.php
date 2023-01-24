<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package core
 */
abstract class PluginLifecycleEvent extends Event
{
    public function __construct(private readonly PluginEntity $plugin)
    {
    }

    public function getPlugin(): PluginEntity
    {
        return $this->plugin;
    }
}
