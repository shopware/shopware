<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type OpenApiSpec array{paths: array<string,array<mixed>>, components: array<mixed>}
 */
#[Package('framework')]
class OpenApiUnusedSchemaRemover
{
    public const COMPONENTS_SCHEMAS_PREFIX = '#/components/schemas/';

    /**
     * @var OpenApiSpec
     */
    private array $specs;

    /**
     * @var array<int|string>
     */
    private array $usedComponents = [];

    /**
     * @param OpenApiSpec $specs
     */
    public function __construct(array $specs)
    {
        $this->specs = $specs;
    }

    /**
     * @return array{paths: array<string,array<mixed>>, components: array<mixed>}
     */
    public function removeUnusedComponentSchemas(): array
    {
        $this->collectAllRefs();
        $this->removeUnusedComponents();

        return $this->specs;
    }

    private function collectAllRefs(): void
    {
        foreach ($this->specs['paths'] as $path) {
            foreach ($path as $method) {
                foreach (['parameters', 'responses', 'requestBody'] as $key) {
                    if (isset($method[$key])) {
                        $this->collectRefs($method[$key]);
                    }
                }
            }
        }

        foreach ($this->specs['components']['schemas'] as $component) {
            $this->collectRefs($component);
        }

        $this->collectNestedRefs();
    }

    /**
     * @param array<mixed> $data
     */
    private function collectRefs(array $data): void
    {
        foreach ($data as $key => $value) {
            if ($key === '$ref' && !\in_array($value, $this->usedComponents, true)) {
                $this->usedComponents[] = $value;
            } elseif (\is_array($value)) {
                $this->collectRefs($value);
            }
        }
    }

    private function collectNestedRefs(): void
    {
        $checkedComponents = [];
        while ($this->usedComponents !== $checkedComponents) {
            $newRefs = array_diff($this->usedComponents, $checkedComponents);
            foreach ($newRefs as $ref) {
                $componentName = $this->getComponentNameFromRef((string) $ref);
                if (isset($this->specs['components']['schemas'][$componentName])) {
                    $this->collectRefs($this->specs['components']['schemas'][$componentName]);
                }
            }
            $checkedComponents = $this->usedComponents;
        }
    }

    private function getComponentNameFromRef(string $ref): string
    {
        return str_replace(self::COMPONENTS_SCHEMAS_PREFIX, '', $ref);
    }

    private function getRefWithComponentName(string $componentName): string
    {
        return self::COMPONENTS_SCHEMAS_PREFIX . $componentName;
    }

    private function removeUnusedComponents(): void
    {
        $componentsToRemove = [];

        foreach ($this->specs['components']['schemas'] as $componentName => $component) {
            if ($componentName === 'failure') {
                continue;
            }
            if (!\in_array($this->getRefWithComponentName($componentName), $this->usedComponents, true)) {
                $componentsToRemove[] = $componentName;
            }
        }

        foreach ($componentsToRemove as $componentName) {
            if (!isset($this->specs['components']['schemas'][$componentName])) {
                continue;
            }

            $this->removeRefsRecursively($componentName);
            unset($this->specs['components']['schemas'][$componentName]);
        }
    }

    /**
     * @param array<mixed>|null $data
     */
    private function removeRefsRecursively(string $componentName, ?array $data = null): void
    {
        if (!isset($this->specs['components']['schemas'][$componentName])) {
            return;
        }
        if ($data === null) {
            $data = $this->specs['components']['schemas'][$componentName];
        }
        foreach ($data as $key => $value) {
            if ($key === '$ref' && !$this->isRefUsedElsewhere($value, $componentName)) {
                $childComponentName = $this->getComponentNameFromRef($value);
                unset($this->specs['components']['schemas'][$childComponentName]);
            } elseif (\is_array($value)) {
                $this->removeRefsRecursively($componentName, $value);
            }
        }
    }

    private function isRefUsedElsewhere(string $ref, string $currentComponentName): bool
    {
        foreach ($this->specs['paths'] as $path) {
            foreach ($path as $method) {
                if ($this->isRefInArray($method, $ref)) {
                    return true;
                }
            }
        }

        foreach ($this->specs['components']['schemas'] as $componentName => $component) {
            if ($componentName !== $currentComponentName && $this->isRefInArray($component, $ref)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $array
     */
    private function isRefInArray(array $array, string $ref): bool
    {
        foreach ($array as $key => $value) {
            if ($key === '$ref' && $value === $ref) {
                return true;
            }

            if (\is_array($value) && $this->isRefInArray($value, $ref)) {
                return true;
            }
        }

        return false;
    }
}
