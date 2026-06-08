<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<FileNode>
 *
 * @internal
 */
#[Package('framework')]
class ServiceRegisteredInCorrectBundleRule implements Rule
{
    private const BUNDLES = [
        'Administration' => 'Shopware\\Administration',
        'Core' => 'Shopware\\Core',
        'Elasticsearch' => 'Shopware\\Elasticsearch',
        'Storefront' => 'Shopware\\Storefront',
    ];

    private readonly string $projectRoot;

    private readonly string $triggerFile;

    public function __construct(?string $projectRoot = null, ?string $triggerFile = null)
    {
        $this->projectRoot = $projectRoot ?? \dirname(__DIR__, 6);
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
        // The XML scan is project-wide, so run it once from this stable rule file.
        if (!$this->isTriggerFile($scope->getFile())) {
            return [];
        }

        $errors = [];
        foreach ($this->getServiceDefinitionFiles() as $file) {
            $currentBundle = $this->getBundleForFile($file);

            if ($currentBundle === null) {
                continue;
            }

            foreach ($this->getServiceDefinitions($file) as $service) {
                if ($service->hasAttribute('alias')) {
                    continue;
                }

                $serviceId = $service->getAttribute('id');
                $serviceClass = $service->getAttribute('class') ?: $serviceId;
                $expectedBundle = $this->getBundleForClass($serviceClass);

                if ($serviceId === '' || $expectedBundle === null || $expectedBundle === $currentBundle) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(\sprintf(
                    'Service "%s" is registered in %s but its effective class "%s" belongs to %s (%s:%d). Register it in a %s DependencyInjection file instead.',
                    $serviceId,
                    $currentBundle,
                    $serviceClass,
                    $expectedBundle,
                    $this->getRelativePath($file),
                    $service->getLineNo(),
                    $expectedBundle
                ))
                    ->line(1)
                    ->identifier('shopware.serviceRegisteredInWrongBundle')
                    ->build();
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
        $srcDir = $this->projectRoot . '/src';

        if (!is_dir($srcDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if (!$file->isFile() || $file->getExtension() !== 'xml') {
                continue;
            }

            $path = $file->getPathname();

            if (!str_contains($path, '/DependencyInjection/') && !preg_match('#/Resources/config/services(?:_[^/]*)?\.xml$#', $path)) {
                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    private function getBundleForFile(string $file): ?string
    {
        foreach (array_keys(self::BUNDLES) as $bundle) {
            if (str_contains($file, '/src/' . $bundle . '/')) {
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

    /**
     * @return list<\DOMElement>
     */
    private function getServiceDefinitions(string $file): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            if (!$document->load($file, \LIBXML_NONET)) {
                return [];
            }

            $xpath = new \DOMXPath($document);
            $services = $xpath->query('//*[local-name() = "service"]');

            if ($services === false) {
                return [];
            }

            $elements = [];
            foreach ($services as $service) {
                if ($service instanceof \DOMElement) {
                    $elements[] = $service;
                }
            }

            return $elements;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function getRelativePath(string $file): string
    {
        if (str_starts_with($file, $this->projectRoot . '/')) {
            return substr($file, \strlen($this->projectRoot) + 1);
        }

        return $file;
    }
}
