<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('framework')]
class PublicServiceDecoratorRule implements Rule
{
    use InTestClassTrait;

    private array $serviceDefinitions = [];
    private bool $servicesLoaded = false;
    private ?string $testServicesPath = null;

    public function __construct(?string $testServicesPath = null)
    {
        $this->testServicesPath = $testServicesPath;
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isInTestClass($scope) || !$scope->isInClass()) {
            return [];
        }

        $class = $scope->getClassReflection();
        $className = $class->getName();

        // Load service definitions if not already loaded
        if (!$this->servicesLoaded) {
            $this->loadServiceDefinitions();
            $this->servicesLoaded = true;
        }

        // Check if this class is a decorator
        if (!$this->isDecoratorService($className)) {
            return [];
        }

        $decoratedService = $this->getDecoratedService($className);
        if (!$decoratedService) {
            return [];
        }

        // Check if the decorated service is public
        if (!$this->isServicePublic($decoratedService)) {
            return [];
        }

        // Check if the decorator itself is public
        if ($this->isServicePublic($className)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Service "%s" decorates the public service "%s" but is not marked as public. Decorators of public services must also be public.',
                    $className,
                    $decoratedService
                )
            )
            ->identifier('shopware.publicServiceDecorator')
            ->build(),
        ];
    }

    private function loadServiceDefinitions(): void
    {
        $serviceFiles = $this->findServiceFiles();

        foreach ($serviceFiles as $file) {
            $this->parseServiceFile($file);
        }
    }

    private function findServiceFiles(): array
    {
        // If test services path is provided, use only that
        if ($this->testServicesPath && file_exists($this->testServicesPath)) {
            return [$this->testServicesPath];
        }

        $projectRoot = dirname(__DIR__, 6); // Navigate up from Rules directory
        $serviceFiles = [];

        // Find all services.xml files
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($projectRoot . '/src', \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'services.xml') {
                $serviceFiles[] = $file->getPathname();
            }
        }

        return $serviceFiles;
    }

    private function parseServiceFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        // Use simple XML parsing to extract service definitions
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        
        if ($xml === false) {
            return;
        }

        foreach ($xml->services->service ?? [] as $service) {
            $id = (string) $service['id'];
            $decorates = (string) $service['decorates'];
            $public = (string) $service['public'];

            if ($id) {
                $this->serviceDefinitions[$id] = [
                    'decorates' => $decorates ?: null,
                    'public' => $public === 'true',
                ];
            }
        }
    }

    private function isDecoratorService(string $className): bool
    {
        return isset($this->serviceDefinitions[$className]) 
            && $this->serviceDefinitions[$className]['decorates'] !== null;
    }

    private function getDecoratedService(string $className): ?string
    {
        return $this->serviceDefinitions[$className]['decorates'] ?? null;
    }

    private function isServicePublic(string $serviceName): bool
    {
        return $this->serviceDefinitions[$serviceName]['public'] ?? false;
    }
}