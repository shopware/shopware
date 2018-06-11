<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

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

    public function add(Plugin $plugin): void
    {
        $class = get_class($plugin);
        $class = substr($class, 0, strpos($class, '\\'));

        if ($this->has($class)) {
            return;
        }

        $this->plugins[$class] = $plugin;
    }

    /**
     * @param Plugin[] $plugins
     */
    public function addList(array $plugins): void
    {
        array_map([$this, 'add'], $plugins);
    }

    public function has($name): bool
    {
        return array_key_exists($name, $this->plugins);
    }

    public function get($name): ?Plugin
    {
        return $this->has($name) ? $this->plugins[$name] : null;
    }

    /**
     * @return Plugin[]
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * @return Plugin[]
     */
    public function getActivePlugins(): array
    {
        return array_filter($this->plugins, function (Plugin $plugin) {
            return $plugin->isActive();
        });
    }

    public function filter(Closure $closure)
    {
        return new static(array_filter($this->plugins, $closure));
    }
}
