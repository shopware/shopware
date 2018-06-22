<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Closure;
use Shopware\Core\Framework\Plugin;

class BundleCollection
{
    /**
     * @var Plugin[]
     */
    private $bundles;

    /**
     * @param Plugin[] $bundles
     */
    public function __construct(array $bundles = [])
    {
        $this->bundles = $bundles;
    }

    public function add(Plugin $bundle): void
    {
        $class = get_class($bundle);
        $class = substr($class, 0, strpos($class, '\\'));

        if ($this->has($class)) {
            return;
        }

        $this->bundles[$class] = $bundle;
    }

    /**
     * @param Plugin[] $bundle
     */
    public function addList(array $bundle): void
    {
        array_map([$this, 'add'], $bundle);
    }

    public function has($name): bool
    {
        return array_key_exists($name, $this->bundles);
    }

    public function get($name): ?Plugin
    {
        return $this->has($name) ? $this->bundles[$name] : null;
    }

    /**
     * @return Plugin[]
     */
    public function all(): array
    {
        return $this->bundles;
    }

    /**
     * @return Plugin[]
     */
    public function getActives(): array
    {
        return array_filter($this->bundles, function (Plugin $plugin) {
            return $plugin->isActive();
        });
    }

    public function filter(Closure $closure)
    {
        return new static(array_filter($this->bundles, $closure));
    }
}
