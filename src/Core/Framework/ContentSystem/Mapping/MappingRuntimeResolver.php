<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\MappingRuntimeDiagnosticCollector;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * Admits and resolves mappings without choosing whole-field fallback or inline text behavior.
 * Provider errors propagate; missing data stays distinct from admission and runtime type rejection.
 *
 * @internal
 */
#[Package('framework')]
final readonly class MappingRuntimeResolver
{
    public function __construct(
        private AbstractContentSystemMappingCandidateRegistry $candidateRegistry,
        private StoredMappingInspector $mappingInspector,
    ) {
    }

    public function resolveProperty(
        string $rootSource,
        string $propertyKey,
        ContextType $contextType,
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
        ?MappingRuntimeDiagnosticCollector $mappingDiagnostics = null,
        ?string $destinationKey = null,
    ): MappingSourceResolution|MappingRejection {
        $candidate = $this->mappingInspector->admitStoredProperty(
            $context->element->component,
            $propertyKey,
            $contextType,
            $source,
            $rootSource,
        );
        if ($candidate instanceof ContentSystemException) {
            return MappingRejection::Unavailable;
        }

        return $this->resolveCandidate($rootSource, $candidate, $source, $context, $mappingDiagnostics, $destinationKey ?? $propertyKey);
    }

    public function resolveInline(
        string $rootSource,
        string $path,
        MappingSourceResolutionContext $context,
        ?MappingRuntimeDiagnosticCollector $mappingDiagnostics = null,
        string $propertyKey = '',
    ): MappingSourceResolution|MappingRejection {
        $candidate = $this->mappingInspector->admitInlinePath($path, $rootSource);
        if ($candidate instanceof ContentSystemException) {
            return MappingRejection::Unavailable;
        }

        return $this->resolveCandidate($rootSource, $candidate, $candidate->source, $context, $mappingDiagnostics, $propertyKey);
    }

    private function resolveCandidate(
        string $rootSource,
        MappingCandidate $candidate,
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
        ?MappingRuntimeDiagnosticCollector $mappingDiagnostics,
        string $propertyKey,
    ): MappingSourceResolution|MappingRejection {
        $resolved = $this->candidateRegistry->resolveSource($rootSource, $source, $context);
        $path = $source->displayName();

        if (!$resolved->hasValue) {
            $mappingDiagnostics?->record(
                $context->element->id,
                $propertyKey,
                $path,
                \sprintf('The mapped source "%s" has no value.', $path),
            );

            return $resolved;
        }

        if (!$this->mappingInspector->acceptsResolvedValue($candidate, $resolved->value)) {
            $mappingDiagnostics?->record(
                $context->element->id,
                $propertyKey,
                $path,
                \sprintf('The mapped source "%s" returned a value incompatible with its candidate type.', $path),
            );

            return MappingRejection::Incompatible;
        }

        return $resolved;
    }
}
