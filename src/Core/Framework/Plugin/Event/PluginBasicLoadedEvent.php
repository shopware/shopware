<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Plugin\Collection\PluginBasicCollection;

class PluginBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'plugin.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var PluginBasicCollection
     */
    protected $plugins;

    public function __construct(PluginBasicCollection $plugins, Context $context)
    {
        $this->context = $context;
        $this->plugins = $plugins;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getPlugins(): PluginBasicCollection
    {
        return $this->plugins;
    }
}
