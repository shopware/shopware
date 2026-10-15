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
 * storefront accessors or reshaping; the default reader preserves existing one-hop and Struct-path providers.
 * See {@see MappingCandidate} for why exposure is the provider's responsibility.
 */
#[Package('framework')]
abstract class AbstractMappingCandidateProvider
{
    /**
     * Declare stable candidate display names by root source for container-build collision checks. Use `*` for
     * candidates offered for every root source. Providers whose candidates come from persisted/runtime data return
     * an empty map and keep validating collisions in that dynamic data themselves.
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
     * Resolve a root reference offered by this provider. The registry checks exact catalogue identity before
     * dispatch, so this fallback is limited to a path the provider explicitly offered. Providers with translated
     * or inherited values should override this with storefront-facing accessors.
     */
    public function resolveRootSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        if ($source->type !== 'root') {
            return MappingSourceResolution::noValue();
        }

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
     * Whether this provider can resolve the supplied non-root source reference. Candidate catalogues remain the
     * admission boundary: the renderer calls this only on the provider that offered the stored reference for the
     * current root source.
     */
    public function supportsSource(MappingSourceReference $source): bool
    {
        return false;
    }

    /**
     * Resolve an admitted non-root reference. Return `noValue()` when source data is missing and `resolved(null)`
     * when it explicitly resolves to null. The registry logs provider exceptions and returns a failed result.
     */
    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        return MappingSourceResolution::noValue();
    }
}
