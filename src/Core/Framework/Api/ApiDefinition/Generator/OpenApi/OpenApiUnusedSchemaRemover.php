<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-import-type OpenApiSpec from DefinitionService
 */
#[Package('framework')]
class OpenApiUnusedSchemaRemover
{
    /**
     * @param OpenApiSpec $specs
     */
    public function removeNotUsedComponentSchemas(array $specs): array
    {
        $usedComponents = [];

        // Collect all $refs in paths
        foreach ($specs['paths'] as $path) {
            foreach ($path as $method) {
                if (isset($method['responses'])) {
                    $this->collectRefs($method['responses'], $usedComponents);
                }
                if (isset($method['requestBody'])) {
                    $this->collectRefs($method['requestBody'], $usedComponents);
                }
            }
        }

        // Collect all $refs in components
        foreach ($specs['components']['schemas'] as $component) {
            $this->collectRefs($component, $usedComponents);
        }

        // Ensure all referenced components are also checked for their references
        $allUsedComponents = $usedComponents;
        $this->collectNestedRefs($specs, $allUsedComponents);

        $finalSpecs = $specs;
        $this->removeUnusedComponents($finalSpecs, $allUsedComponents);

        return $finalSpecs;
    }

    private function collectRefs(array $data, array &$usedComponents): void
    {
        foreach ($data as $key => $value) {
            if ($key === '$ref' && \in_array($value, $usedComponents, true) === false) {
                $usedComponents[] = $value;
            } elseif (\is_array($value)) {
                $this->collectRefs($value, $usedComponents);
            }
        }
    }

    private function collectNestedRefs(array &$finalSpecs, array &$allUsedComponents): void
    {
        $checkedComponents = [];
        while ($allUsedComponents !== $checkedComponents) {
            $newRefs = array_diff($allUsedComponents, $checkedComponents);
            foreach ($newRefs as $ref) {
                $componentName = str_replace('#/components/schemas/', '', $ref);
                if (isset($finalSpecs['components']['schemas'][$componentName])) {
                    $this->collectRefs($finalSpecs['components']['schemas'][$componentName], $allUsedComponents);
                }
            }
            $checkedComponents = $allUsedComponents;
        }
    }

    private function removeUnusedComponents(array &$finalSpecs, array &$allUsedComponents): void
    {
        $componentsToRemove = [];

        foreach ($finalSpecs['components']['schemas'] as $componentName => $component) {
            if ($componentName === 'failure') {
                continue;
            }
            if (!\in_array('#/components/schemas/' . $componentName, $allUsedComponents, true)) {
                $componentsToRemove[] = $componentName;
            }
        }

        foreach ($componentsToRemove as $componentName) {
            $this->removeNestedRefs($finalSpecs, $componentName, $allUsedComponents);
            unset($finalSpecs['components']['schemas'][$componentName]);
        }
    }

    private function removeNestedRefs(array &$finalSpecs, string $componentName, array &$allUsedComponents): void
    {
        if (!isset($finalSpecs['components']['schemas'][$componentName])) {
            return;
        }

        $component = $finalSpecs['components']['schemas'][$componentName];

        if (isset($component['properties'])) {
            foreach ($component['properties'] as $propertyName => $property) {
                if (isset($property['$ref']) && !$this->isRefUsedElsewhere($finalSpecs, $property['$ref'], $componentName)) {
                    unset($finalSpecs['components']['schemas'][$propertyName]);
                }
            }
        }
    }

    private function isRefUsedElsewhere(array $finalSpecs, string $ref, string $currentComponentName): bool
    {
        foreach ($finalSpecs['paths'] as $path) {
            foreach ($path as $method) {
                if ($this->isRefInArray($method, $ref)) {
                    return true;
                }
            }
        }

        foreach ($finalSpecs['components']['schemas'] as $componentName => $component) {
            if ($componentName !== $currentComponentName && $this->isRefInArray($component, $ref)) {
                return true;
            }
        }

        return false;
    }

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
