<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\KernelPluginLoader;

use Composer\Autoload\ClassLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Exception\KernelPluginLoaderException;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;

#[Package('core')]
abstract class KernelPluginLoader extends Bundle
{
    /**
     * @var array<int, mixed>
     */
    protected $pluginInfos = [];

    private readonly KernelPluginCollection $pluginInstances;

    private readonly string $pluginDir;

    private bool $initialized = false;

    /**
     * @internal
     */
    public function __construct(
        private readonly ClassLoader $classLoader,
        ?string $pluginDir = null
    ) {
        $this->pluginDir = $pluginDir ?? 'custom/plugins';
        $this->pluginInstances = new KernelPluginCollection();
    }

    final public function getPluginDir(string $projectDir): string
    {
        // absolute path
        if (mb_strpos($this->pluginDir, '/') === 0) {
            return $this->pluginDir;
        }

        return $projectDir . '/' . $this->pluginDir;
    }

    /**
     * @return array<int, mixed>
     * Basic information required for instantiating the plugins
     */
    final public function getPluginInfos(): array
    {
        return $this->pluginInfos;
    }

    /**
     * @final
     * Instances of the plugin bundle classes
     */
    public function getPluginInstances(): KernelPluginCollection
    {
        return $this->pluginInstances;
    }

    /**
     * @param array<string, mixed> $kernelParameters
     * @param array<int, string> $loadedBundles
     *
     * @return \Traversable<Bundle>
     */
    final public function getBundles(array $kernelParameters = [], array $loadedBundles = []): iterable
    {
        if (!$this->initialized) {
            return;
        }

        foreach ($this->pluginInstances->getActives() as $plugin) {
            $copy = new KernelPluginCollection($this->getPluginInstances()->all());
            $additionalBundleParameters = new AdditionalBundleParameters($this->classLoader, $copy, $kernelParameters);
            $additionalBundles = $plugin->getAdditionalBundles($additionalBundleParameters);
            [$preLoaded, $postLoaded] = $this->splitBundlesIntoPreAndPost($additionalBundles);

            foreach ([...\array_values($preLoaded), $plugin, ...\array_values($postLoaded)] as $bundle) {
                if (!\in_array($bundle->getName(), $loadedBundles, true)) {
                    yield $bundle;
                    $loadedBundles[] = $bundle->getName();
                }
            }
        }

        if (!\in_array($this->getName(), $loadedBundles, true)) {
            yield $this;
        }
    }

    /**
     * @throws KernelPluginLoaderException
     */
    final public function initializePlugins(string $projectDir): void
    {
        if ($this->initialized) {
            return;
        }

        $this->loadPluginInfos();
        if (empty($this->pluginInfos)) {
            $this->initialized = true;

            return;
        }

        $this->registerPluginNamespaces($projectDir);
        $this->instantiatePlugins($projectDir);

        $this->initialized = true;
    }

    final public function build(ContainerBuilder $container): void
    {
        if (!$this->initialized) {
            return;
        }

        parent::build($container);

        /*
         * Register every plugin in the di container, enable autowire and set public
         */
        foreach ($this->pluginInstances->getActives() as $plugin) {
            $class = $plugin::class;

            $definition = new Definition();
            if ($container->hasDefinition($class)) {
                $definition = $container->getDefinition($class);
            }

            $definition->setFactory([new Reference(self::class), 'getPluginInstance']);
            $definition->addArgument($class);

            $definition->setAutowired(true);
            $definition->setPublic(true);

            $container->setDefinition($class, $definition);
        }
    }

    final public function getPluginInstance(string $class): ?Plugin
    {
        $plugin = $this->pluginInstances->get($class);
        if (!$plugin || !$plugin->isActive()) {
            return null;
        }

        return $plugin;
    }

    public function getClassLoader(): ClassLoader
    {
        return $this->classLoader;
    }

    abstract protected function loadPluginInfos(): void;

    /**
     * @throws KernelPluginLoaderException
     */
    private function registerPluginNamespaces(string $projectDir): void
    {
        foreach ($this->pluginInfos as $plugin) {
            \assert(\is_string($plugin['baseClass']));
            $pluginName = $plugin['name'] ?? $plugin['baseClass'];

            // plugins managed by composer are already in the classMap
            if ($plugin['managedByComposer']) {
                continue;
            }

            if (!isset($plugin['autoload'])) {
                $reason = sprintf(
                    'Unable to register plugin "%s" in autoload. Required property `autoload` missing.',
                    $plugin['baseClass']
                );

                throw new KernelPluginLoaderException($pluginName, $reason);
            }

            $psr4 = $plugin['autoload']['psr-4'] ?? [];
            $psr0 = $plugin['autoload']['psr-0'] ?? [];

            if (empty($psr4) && empty($psr0)) {
                $reason = sprintf(
                    'Unable to register plugin "%s" in autoload. Required property `psr-4` or `psr-0` missing in property autoload.',
                    $plugin['baseClass']
                );

                throw new KernelPluginLoaderException($pluginName, $reason);
            }

            foreach ($psr4 as $namespace => $paths) {
                if (\is_string($paths)) {
                    $paths = [$paths];
                }
                $mappedPaths = $this->mapPsrPaths($pluginName, $paths, $projectDir, $plugin['path']);
                $this->classLoader->addPsr4($namespace, $mappedPaths);
                if ($this->classLoader->isClassMapAuthoritative()) {
                    $this->classLoader->setClassMapAuthoritative(false);
                }
            }

            foreach ($psr0 as $namespace => $paths) {
                if (\is_string($paths)) {
                    $paths = [$paths];
                }
                $mappedPaths = $this->mapPsrPaths($pluginName, $paths, $projectDir, $plugin['path']);

                $this->classLoader->add($namespace, $mappedPaths);
                if ($this->classLoader->isClassMapAuthoritative()) {
                    $this->classLoader->setClassMapAuthoritative(false);
                }
            }
        }
    }

    /**
     * @param array<string> $psr
     *
     * @throws KernelPluginLoaderException
     *
     * @return list<string>
     */
    private function mapPsrPaths(string $plugin, array $psr, string $projectDir, string $pluginRootPath): array
    {
        $mappedPaths = [];

        $absolutePluginRootPath = $this->getAbsolutePluginRootPath($projectDir, $pluginRootPath);

        if (mb_strpos($absolutePluginRootPath, $projectDir) !== 0) {
            throw new KernelPluginLoaderException(
                $plugin,
                sprintf('Plugin dir %s needs to be a sub-directory of the project dir %s', $pluginRootPath, $projectDir)
            );
        }

        foreach ($psr as $path) {
            $mappedPaths[] = $absolutePluginRootPath . '/' . $path;
        }

        return $mappedPaths;
    }

    private function getAbsolutePluginRootPath(string $projectDir, string $pluginRootPath): string
    {
        // is relative path
        if (mb_strpos($pluginRootPath, '/') !== 0) {
            $pluginRootPath = $projectDir . '/' . $pluginRootPath;
        }

        return $pluginRootPath;
    }

    /**
     * @throws KernelPluginLoaderException
     */
    private function instantiatePlugins(string $projectDir): void
    {
        foreach ($this->pluginInfos as $pluginData) {
            $className = $pluginData['baseClass'];

            $pluginClassFilePath = $this->classLoader->findFile($className);
            if (!class_exists($className) || !$pluginClassFilePath || !file_exists($pluginClassFilePath)) {
                continue;
            }

            /** @var Plugin $plugin */
            $plugin = new $className((bool) $pluginData['active'], $pluginData['path'], $projectDir);

            if (!$plugin instanceof Plugin) {
                $reason = sprintf('Plugin class "%s" must extend "%s"', $plugin::class, Plugin::class);

                throw new KernelPluginLoaderException($pluginData['name'], $reason);
            }

            $this->pluginInstances->add($plugin);
        }
    }

    /**
     * @param Bundle[] $bundles
     *
     * @return array<Bundle[]>
     */
    private function splitBundlesIntoPreAndPost(array $bundles): array
    {
        $pre = [];
        $post = [];

        foreach ($bundles as $index => $bundle) {
            if (\is_int($index) && $index < 0) {
                $pre[$index] = $bundle;
            } else {
                $post[$index] = $bundle;
            }
        }

        \ksort($pre);
        \ksort($post);

        return [$pre, $post];
    }
}
