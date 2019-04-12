<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\DummyPlugin;
use Shopware\Core\Framework\PluginInterface;

class KernelPluginCollection
{
    /**
     * @var PluginInterface[]
     */
    private $bundles;

    /**
     * @param PluginInterface[] $bundles
     */
    public function __construct(array $bundles = [])
    {
        $this->bundles = $bundles;
    }

    public function add(PluginInterface $bundle): void
    {
        if ($bundle instanceof DummyPlugin) {
            $class = $bundle->getName();
        } else {
            /** @var string|false $class */
            $class = \get_class($bundle);
        }

        if ($class === false) {
            return;
        }

        if ($this->has($class)) {
            return;
        }

        $this->bundles[$class] = $bundle;
    }

    /**
     * @param PluginInterface[] $bundle
     */
    public function addList(array $bundle): void
    {
        array_map([$this, 'add'], $bundle);
    }

    public function has($name): bool
    {
        return array_key_exists($name, $this->bundles);
    }

    public function get($name): ?PluginInterface
    {
        return $this->has($name) ? $this->bundles[$name] : null;
    }

    /**
     * @return PluginInterface[]
     */
    public function all(): array
    {
        return $this->bundles;
    }

    /**
     * @return PluginInterface[]
     */
    public function getActives(): array
    {
        return array_filter($this->bundles, function (PluginInterface $plugin) {
            return $plugin->isActive();
        });
    }

    public function filter(\Closure $closure)
    {
        return new static(array_filter($this->bundles, $closure));
    }
}
