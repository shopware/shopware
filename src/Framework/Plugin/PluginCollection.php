<?php

namespace Shopware\Framework\Plugin;

use Closure;

class PluginCollection
{
    /**
     * @var Plugin[]
     */
    private $plugins;

    /**
     * @param Plugin[] $plugins
     */
    public function __construct(array $plugins = [])
    {
        $this->plugins = $plugins;
    }

    public function add(Plugin $plugin)
    {
        $class = get_class($plugin);

        if ($this->has($class)) {
            return;
        }

        $this->plugins[$class] = $plugin;
    }

    /**
     * @param Plugin[] $plugins
     */
    public function addList(array $plugins)
    {
        array_map([$this, 'add'], $plugins);
    }

    public function has($name)
    {
        return array_key_exists($name, $this->plugins);
    }

    /**
     * @return Plugin[]
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function filter(Closure $closure)
    {
        return new static(array_filter($this->plugins, $closure));
    }
}