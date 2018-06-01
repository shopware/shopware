<?php declare(strict_types=1);

namespace Shopware\Framework\Plugin\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Plugin\Collection\PluginBasicCollection;

class PluginBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'plugin.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
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
