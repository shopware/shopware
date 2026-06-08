<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @implements Rule<FileNode>
 *
 * @internal
 */
#[Package('framework')]
class ServiceDefinitionRule implements Rule
{
    private const BUNDLES = [
        'Administration' => 'Shopware\\Administration',
        'Core' => 'Shopware\\Core',
        'Elasticsearch' => 'Shopware\\Elasticsearch',
        'Storefront' => 'Shopware\\Storefront',
    ];

    private readonly string $projectRoot;

    private readonly string $triggerFile;

    public function __construct(
        private readonly ServiceMap $serviceMap,
        ?string $projectRoot = null,
        ?string $triggerFile = null
    ) {
        $this->projectRoot = $projectRoot ?? (\defined('TEST_PROJECT_DIR') ? TEST_PROJECT_DIR : \dirname(__DIR__, 6));
        $this->triggerFile = $triggerFile ?? __FILE__;
    }

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @param FileNode $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isTriggerFile($scope->getFile())) {
            return [];
        }

        $errors = [];

        foreach ($this->getServiceDefinitionFiles() as $file) {
            $relativePath = $this->getRelativePath($file);

            try {
                $serviceIds = $this->getDeclaredServiceIds($file);
            } catch (\Throwable $e) {
                $errors[] = $this->buildError(\sprintf(
                    '%s - could not load service definitions: %s',
                    $relativePath,
                    $e->getMessage()
                ));

                continue;
            }

            foreach ($serviceIds as $serviceId) {
                $errors = array_merge(
                    $errors,
                    $this->checkServiceBundleRegistration($serviceId, $relativePath)
                );
            }
        }

        return $errors;
    }

    private function isTriggerFile(string $file): bool
    {
        $realFile = realpath($file);
        $realTriggerFile = realpath($this->triggerFile);

        return $realFile !== false && $realTriggerFile !== false && $realFile === $realTriggerFile;
    }

    /**
     * @return list<string>
     */
    private function getServiceDefinitionFiles(): array
    {
        return $this->getFiles(fn (string $path): bool => $this->isXmlServiceDefinitionFile($path) || $this->isPhpServiceDefinitionFile($path));
    }

    private function isXmlServiceDefinitionFile(string $path): bool
    {
        return str_ends_with($path, '.xml')
            && (str_contains($path, '/DependencyInjection/') || preg_match('#/Resources/config/services(?:_[^/]*)?\.xml$#', $path) === 1);
    }

    private function isPhpServiceDefinitionFile(string $path): bool
    {
        if (!str_ends_with($path, '.php')) {
            return false;
        }

        if (!str_contains($path, '/DependencyInjection/') && preg_match('#/Resources/config/services(?:_[^/]*)?\.php$#', $path) !== 1) {
            return false;
        }

        $content = file_get_contents($path);

        return $content !== false && str_contains($content, 'ContainerConfigurator');
    }

    /**
     * @param \Closure(string): bool $filter
     *
     * @return list<string>
     */
    private function getFiles(\Closure $filter): array
    {
        $srcDir = $this->projectRoot . '/src';

        if (!is_dir($srcDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if ($filter($path)) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function getDeclaredServiceIds(string $file): array
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->projectRoot);
        $container->setParameter('kernel.environment', 'phpstan_dev');
        $container->registerExtension(new MonologExtension());

        $locator = new FileLocator(\dirname($file));
        $xmlLoader = new XmlFileLoader($container, $locator, 'phpstan_dev');
        $phpLoader = new PhpFileLoader($container, $locator, 'phpstan_dev');
        $resolver = new LoaderResolver([$xmlLoader, $phpLoader]);

        $xmlLoader->setResolver($resolver);
        $phpLoader->setResolver($resolver);

        if (str_ends_with($file, '.php')) {
            $phpLoader->load($file);
        } else {
            $xmlLoader->load($file);
        }

        $serviceIds = array_keys($container->getDefinitions());

        return array_values(array_filter(
            $serviceIds,
            static fn (string $serviceId): bool => $serviceId !== 'service_container'
        ));
    }

    /**
     * @return list<RuleError>
     */
    private function checkServiceBundleRegistration(string $serviceId, string $relativePath): array
    {
        $service = $this->serviceMap->getService($serviceId);

        if ($service === null || $service->getAlias() !== null) {
            return [];
        }

        $currentBundle = $this->getBundleForFile($relativePath);
        $serviceClass = $service->getClass() ?? $serviceId;
        $expectedBundle = $this->getBundleForClass($serviceClass);

        if ($serviceId === '' || $currentBundle === null || $expectedBundle === null || $expectedBundle === $currentBundle) {
            return [];
        }

        return [$this->buildError(\sprintf(
            '%s - service "%s" is registered in %s but its effective class "%s" belongs to %s. Register it in a %s DependencyInjection file instead.',
            $relativePath,
            $serviceId,
            $currentBundle,
            $serviceClass,
            $expectedBundle,
            $expectedBundle
        ))];
    }

    private function getBundleForFile(string $relativePath): ?string
    {
        foreach (array_keys(self::BUNDLES) as $bundle) {
            if (str_starts_with($relativePath, 'src/' . $bundle . '/') || str_starts_with($relativePath, $bundle . '/')) {
                return $bundle;
            }
        }

        return null;
    }

    private function getBundleForClass(string $class): ?string
    {
        foreach (self::BUNDLES as $bundle => $namespace) {
            if ($class === $namespace || str_starts_with($class, $namespace . '\\')) {
                return $bundle;
            }
        }

        return null;
    }

    private function getRelativePath(string $file): string
    {
        if (str_starts_with($file, $this->projectRoot . '/')) {
            return substr($file, \strlen($this->projectRoot) + 1);
        }

        return $file;
    }

    private function buildError(string $message): RuleError
    {
        return RuleErrorBuilder::message($message)
            ->line(1)
            ->identifier('shopware.serviceDefinition')
            ->build();
    }
}
