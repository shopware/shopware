<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;

#[Package('core')]
class KernelPluginCollection
{
    /**
     * @internal
     *
     * @param array<class-string<Plugin>, Plugin> $plugins
     */
    public function __construct(private array $plugins = [])
    {
    }

    public function add(Plugin $plugin): void
    {
        $class = $plugin::class;

        if ($this->has($class)) {
            return;
        }

        $this->plugins[$class] = $plugin;
    }

    /**
     * @param list<Plugin> $plugins
     */
    public function addList(array $plugins): void
    {
        foreach ($plugins as $plugin) {
            $this->add($plugin);
        }
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->plugins);
    }

    public function get(string $name): ?Plugin
    {
        return $this->has($name) ? $this->plugins[$name] : null;
    }

    /**
     * @return array<class-string<Plugin>, Plugin>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * @return array<class-string<Plugin>, Plugin>
     */
    public function getActives(): array
    {
        if (!$this->plugins) {
            return [];
        }

        return array_filter($this->plugins, static fn (Plugin $plugin) => $plugin->isActive());
    }

    public function filter(\Closure $closure): KernelPluginCollection
    {
        return new self(array_filter($this->plugins, $closure));
    }
}
