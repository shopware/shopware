<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\BusinessEventRegisterCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Kernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\Bundle as SymfonyBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

#[Package('framework')]
abstract class Bundle extends SymfonyBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->registerContainerFile($container);
        $this->registerMigrationPath($container);
        $this->registerFilesystem($container, 'private');
        $this->registerFilesystem($container, 'public');
        $this->registerEvents($container);
    }

    public function getMigrationNamespace(): string
    {
        return $this->getNamespace() . '\Migration';
    }

    public function getMigrationPath(): string
    {
        $migrationSuffix = str_replace(
            $this->getNamespace(),
            '',
            $this->getMigrationNamespace()
        );

        return $this->getPath() . str_replace('\\', '/', $migrationSuffix);
    }

    /**
     * Returns the PHP class namespace used to register Twig components for this bundle with
     * Symfony UX TwigComponent. Override this method to use a different namespace structure.
     */
    public static function getTwigComponentNamespace(): string
    {
        $class = static::class;
        $pos = strrpos($class, '\\');

        return ($pos !== false ? substr($class, 0, $pos) : '') . '\\Resources\\views\\components\\';
    }

    final public function getContainerPrefix(): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($this->getName());
    }

    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        $confDir = $this->getPath() . '/Resources/config';

        if (\is_dir($confDir)) {
            // @deprecated tag:v6.8.0 - remove the deprecation trigger, XML route definitions are no longer loaded
            foreach ([...$this->getXmlFilesRecursive($confDir . '/routes'), $confDir . '/routes.xml', $confDir . '/routes_' . $environment . '.xml'] as $path) {
                if (is_file($path)) {
                    $this->triggerXmlConfigDeprecation($path, \sprintf('Migrate the route definitions to PHP format (%s).', basename($path, '.xml') . '.php'));
                }
            }

            $routes->import($confDir . '/{routes}/*' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($confDir . '/{routes}/' . $environment . '/**/*' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($confDir . '/{routes}' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($confDir . '/{routes}_' . $environment . Kernel::CONFIG_EXTS, 'glob');
        }
    }

    /**
     * @return list<SymfonyBundle>
     */
    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return [];
    }

    public function configureRouteOverwrites(RoutingConfigurator $routes, string $environment): void
    {
        $fileSystem = new Filesystem();
        $confDir = $this->getPath() . '/Resources/config';

        if ($fileSystem->exists($confDir)) {
            // @deprecated tag:v6.8.0 - remove the deprecation trigger, XML route definitions are no longer loaded
            if (is_file($confDir . '/routes_overwrite.xml')) {
                $this->triggerXmlConfigDeprecation($confDir . '/routes_overwrite.xml', 'Migrate the route definitions to PHP format (routes_overwrite.php).');
            }

            $routes->import($confDir . '/{routes_overwrite}' . Kernel::CONFIG_EXTS, 'glob');
        }
    }

    public function getTemplatePriority(): int
    {
        return 0;
    }

    /**
     * Used to configure the BaseUrl for the Admin Extension API
     */
    public function getAdminBaseUrl(): ?string
    {
        return null;
    }

    /**
     * Returns a list of all action event class references of this bundle. The events will be registered inside the `\Shopware\Core\Framework\Event\BusinessEventRegistry`.
     *
     * @return array<class-string>
     */
    protected function getActionEventClasses(): array
    {
        return [];
    }

    protected function registerMigrationPath(ContainerBuilder $container): void
    {
        $migrationPath = $this->getMigrationPath();

        if (!is_dir($migrationPath)) {
            return;
        }

        $container->register(MigrationSource::class . '_' . $this->getName(), MigrationSource::class)
            ->addArgument($this->getName())
            ->addArgument([$migrationPath => $this->getMigrationNamespace()])
            ->addTag('shopware.migration_source');
    }

    protected function buildDefaultConfig(ContainerBuilder $container): void
    {
        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            // @deprecated tag:v6.8.0 - XML configuration is deprecated, remove the XmlFileLoader together with the deprecation
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = $this->getPath() . '/Resources/config';

        // @deprecated tag:v6.8.0 - remove the deprecation trigger, XML package configuration is no longer loaded
        foreach ($this->getXmlFilesRecursive($confDir . '/packages') as $path) {
            $this->triggerXmlConfigDeprecation($path, 'Migrate the package configuration to YAML or PHP format.');
        }

        $configLoader->load($confDir . '/{packages}/*' . Kernel::CONFIG_EXTS, 'glob');

        $env = $container->getParameter('kernel.environment');
        \assert(\is_string($env));

        $configLoader->load($confDir . '/{packages}/' . $env . '/*' . Kernel::CONFIG_EXTS, 'glob');
    }

    private function registerFilesystem(ContainerBuilder $container, string $key): void
    {
        $containerPrefix = $this->getContainerPrefix();
        $parameterKey = \sprintf('shopware.filesystem.%s', $key);
        $serviceId = \sprintf('%s.filesystem.%s', $containerPrefix, $key);

        $filesystem = new Definition(
            PrefixFilesystem::class,
            [
                new Reference($parameterKey),
                'plugins/' . $containerPrefix,
            ]
        );
        $filesystem->setPublic(true);

        $container->setDefinition($serviceId, $filesystem);

        // SwagMigrationAssistant -> swagMigrationAssistantPublicFilesystem
        $aliasName = (new CamelCaseToSnakeCaseNameConverter())->denormalize($this->getName()) . ucfirst($key) . 'Filesystem';
        $container->registerAliasForArgument($serviceId, FilesystemOperator::class, $aliasName);
    }

    private function registerEvents(ContainerBuilder $container): void
    {
        $classes = $this->getActionEventClasses();

        if ($classes === []) {
            return;
        }

        $container->addCompilerPass(new BusinessEventRegisterCompilerPass($classes), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }

    /**
     * Looks for service definition files inside the `Resources/config`
     * directory and loads either xml or yml files.
     */
    private function registerContainerFile(ContainerBuilder $container): void
    {
        $fileLocator = new FileLocator($this->getPath());
        $loaderResolver = new LoaderResolver([
            // @deprecated tag:v6.8.0 - XML service definitions are deprecated, remove the XmlFileLoader together with the deprecation
            new XmlFileLoader($container, $fileLocator),
            new YamlFileLoader($container, $fileLocator),
            new PhpFileLoader($container, $fileLocator),
        ]);
        $delegatingLoader = new DelegatingLoader($loaderResolver);

        foreach ($this->getServicesFilePathArray($this->getPath() . '/Resources/config/services.*') as $path) {
            // @deprecated tag:v6.8.0 - remove the deprecation trigger, XML service definitions are no longer loaded
            $this->triggerXmlConfigDeprecation($path, 'Migrate the service definitions to PHP format (services.php).');
            $delegatingLoader->load($path);
        }

        if ($container->getParameter('kernel.environment') === 'test') {
            foreach ($this->getServicesFilePathArray($this->getPath() . '/Resources/config/services_test.*') as $testPath) {
                // @deprecated tag:v6.8.0 - remove the deprecation trigger, XML service definitions are no longer loaded
                $this->triggerXmlConfigDeprecation($testPath, 'Migrate the service definitions to PHP format (services_test.php).');
                $delegatingLoader->load($testPath);
            }
        }
    }

    // @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
    private function triggerXmlConfigDeprecation(string $path, string $migrationHint): void
    {
        if (!str_ends_with($path, '.xml')) {
            return;
        }

        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'The XML configuration file "%s" in bundle "%s" is deprecated and will not be loaded in v6.8.0.0. %s',
                $path,
                $this->getName(),
                $migrationHint,
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function getXmlFilesRecursive(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach ((new Finder())->files()->in($dir)->name('*.xml')->sortByName() as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function getServicesFilePathArray(string $path): array
    {
        $pathArray = glob($path);

        if ($pathArray === false) {
            return [];
        }

        return $pathArray;
    }
}
