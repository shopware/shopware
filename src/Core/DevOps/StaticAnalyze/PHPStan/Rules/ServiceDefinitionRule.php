<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
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
 * @phpstan-import-type ServiceDefinitionCollectorData from ServiceDefinitionCollector
 *
 * @implements Rule<CollectedDataNode>
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

    /**
     * @var array<string, list<array{file: string, relativePath: string}>>|null
     */
    private ?array $declarationsByServiceId = null;

    /**
     * @var list<array{file: string, relativePath: string, message: string}>|null
     */
    private ?array $loadingErrors = null;

    public function __construct(
        private readonly ServiceMap $serviceMap,
        ?string $projectRoot = null
    ) {
        $this->projectRoot = $projectRoot ?? (\defined('TEST_PROJECT_DIR') ? TEST_PROJECT_DIR : \dirname(__DIR__, 6));
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @param CollectedDataNode $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];
        $checkedServiceIds = [];

        foreach ($this->getServiceDefinitionLoadingErrors() as $loadingError) {
            $errors[] = $this->buildError(\sprintf(
                '%s - could not load service definitions: %s',
                $loadingError['relativePath'],
                $loadingError['message']
            ), $loadingError['file']);
        }

        /** @var array<string, list<list<ServiceDefinitionCollectorData>>> $collectedServiceDefinitions */
        $collectedServiceDefinitions = $node->get(ServiceDefinitionCollector::class);

        foreach ($collectedServiceDefinitions as $serviceDefinitionGroups) {
            foreach ($serviceDefinitionGroups as $serviceDefinitions) {
                foreach ($serviceDefinitions as $serviceDefinition) {
                    $serviceId = $serviceDefinition['serviceId'];

                    if (isset($checkedServiceIds[$serviceId])) {
                        continue;
                    }

                    $checkedServiceIds[$serviceId] = true;

                    foreach ($this->checkServiceDeclarationBundle($serviceId) as $error) {
                        $errors[] = $error;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<array{file: string, relativePath: string, message: string}>
     */
    private function getServiceDefinitionLoadingErrors(): array
    {
        if ($this->loadingErrors !== null) {
            return $this->loadingErrors;
        }

        $this->collectServiceDeclarations();

        return $this->loadingErrors ?? [];
    }

    /**
     * @return array<string, list<array{file: string, relativePath: string}>>
     */
    private function getServiceDeclarations(): array
    {
        if ($this->declarationsByServiceId !== null) {
            return $this->declarationsByServiceId;
        }

        $this->collectServiceDeclarations();

        return $this->declarationsByServiceId ?? [];
    }

    private function collectServiceDeclarations(): void
    {
        $this->declarationsByServiceId = [];
        $this->loadingErrors = [];
        $seenDeclarations = [];

        foreach ($this->getServiceDefinitionFiles() as $file) {
            $relativePath = $this->getRelativePath($file);

            try {
                $declaredServiceIds = $this->getDeclaredServiceIds($file);
            } catch (\Throwable $e) {
                $this->loadingErrors[] = [
                    'file' => $file,
                    'relativePath' => $relativePath,
                    'message' => $e->getMessage(),
                ];

                continue;
            }

            foreach ($declaredServiceIds as $serviceId) {
                $declarationKey = $file . ':' . $serviceId;

                if (isset($seenDeclarations[$declarationKey])) {
                    continue;
                }

                $seenDeclarations[$declarationKey] = true;
                $this->declarationsByServiceId[$serviceId][] = [
                    'file' => $file,
                    'relativePath' => $relativePath,
                ];
            }
        }
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
    private function checkServiceDeclarationBundle(string $serviceId): array
    {
        $service = $this->serviceMap->getService($serviceId);

        if ($service === null || $service->getAlias() !== null) {
            return [];
        }

        $errors = [];
        $serviceClass = $service->getClass() ?? $serviceId;
        $expectedBundle = $this->getBundleForClass($serviceClass);

        foreach ($this->getServiceDeclarations()[$serviceId] ?? [] as $declaration) {
            $currentBundle = $this->getBundleForFile($declaration['relativePath']);

            if ($serviceId === '' || $currentBundle === null || $expectedBundle === null || $expectedBundle === $currentBundle) {
                continue;
            }

            $errors[] = $this->buildError(\sprintf(
                '%s - service "%s" is registered in %s but its effective class "%s" belongs to %s. Register it in a %s DependencyInjection file instead.',
                $declaration['relativePath'],
                $serviceId,
                $currentBundle,
                $serviceClass,
                $expectedBundle,
                $expectedBundle
            ), $declaration['file']);
        }

        return $errors;
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

    private function buildError(string $message, string $file): RuleError
    {
        return RuleErrorBuilder::message($message)
            ->file($file)
            ->line(1)
            ->identifier('shopware.serviceDefinition')
            ->build();
    }
}
