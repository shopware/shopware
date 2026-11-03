<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Provider;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Contributes the mapping candidates one root source offers. Tag an implementation
 * `content_system.mapping_candidate_provider` to have it aggregated by the registry.
 *
 * A provider is asked about one root source at a time, so a provider may serve exactly one (the entity's own
 * provider, owned by the module that owns the entity) or many (a cross-cutting provider that offers the same
 * shape of data for every layout type).
 *
 * Every candidate must be safe to serve publicly. Override {@see resolveRootSource()} for candidate-specific
 * storefront accessors or reshaping; the default reader supports Struct paths.
 * Exposure constraints are documented in `Mapping/docs/source-providers.md`.
 */
#[Package('framework')]
abstract class AbstractMappingCandidateProvider
{
    /**
     * Declare stable candidate display names by root source for container-build collision checks. Use `*` for
     * candidates offered for every root source. Providers whose candidates come from persisted/runtime data return
     * an empty map. The registry excludes and logs runtime collisions across all providers.
     *
     * @return array<string, list<string>>
     */
    abstract public static function registeredCandidateKeys(): array;

    abstract public function supports(string $rootSource): bool;

    /**
     * Called only for a root source this provider answered `true` for.
     *
     * @return list<MappingCandidate>
     */
    abstract public function provide(string $rootSource): array;

    /**
     * Metadata dependencies for an attempted mapping, including a currently unavailable candidate. Called only
     * for a supported root source. Inspect the display name without reading source values or trusting config;
     * this hook does not admit a mapping or authorize resolution.
     *
     * @return list<string>
     */
    public function cacheTagsForMapping(string $rootSource, string $path): array
    {
        return [];
    }

    /**
     * Receives a root reference offered by this provider, with exact source identity and configuration checked
     * by the registry before dispatch. The reader may rely on that precondition. Providers with translated
     * or inherited values should override this with storefront-facing accessors.
     * Provider errors propagate to normal error handling; return `noValue()` only for missing source data.
     */
    public function resolveRootSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        $root = $context->rootValues[$source->id] ?? null;
        if ($source->path === null) {
            return \array_key_exists($source->id, $context->rootValues)
                ? MappingSourceResolution::resolved($root)
                : MappingSourceResolution::noValue();
        }

        if (!$root instanceof Struct) {
            return MappingSourceResolution::noValue();
        }

        $result = (new ContextPathResolver())->resolveMappingPathWithPresence(
            $root,
            explode('.', $source->path),
        );

        return $result['found']
            ? MappingSourceResolution::resolved($result['value'])
            : MappingSourceResolution::noValue();
    }

    /**
     * Receives a non-root reference offered by this provider, with exact source identity and configuration checked
     * by the registry before dispatch. Return `noValue()` when data is missing and `resolved(null)` for a present null.
     * Provider errors propagate to normal error handling.
     */
    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        return MappingSourceResolution::noValue();
    }
}
