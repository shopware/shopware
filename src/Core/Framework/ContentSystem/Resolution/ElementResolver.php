<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Resolution;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ElementResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly AbstractContentSystemDataLoaderMapResolver $mapResolver,
        private readonly DataLoaderConfigSerializerProvider $configSerializers,
        private readonly DataLoaderProvider $dataLoaderProvider,
    ) {
    }

    /**
     * @return list<PropertyResolution>
     */
    public function resolve(StoredElement|string $element, ResolutionContext $context): array
    {
        $type = \is_string($element) ? $element : $element->component;

        if (!$this->registry->has($type)) {
            return [];
        }

        // A string $element carries no stored wiring by design: only a StoredElement instance has
        // dataRequirements, so a type-name-only resolve never produces a Stored candidate.
        $storedRequirements = $element instanceof StoredElement ? $element->dataRequirements : [];

        $resolutions = [];

        foreach ($this->registry->get($type)->properties() as $key => $property) {
            $propertyType = $property->type();
            $declaredType = $propertyType->type();

            if ($propertyType->isPrimitive() || !\is_string($declaredType) || $declaredType === 'object') {
                $resolutions[] = new PropertyResolution(
                    key: $key,
                    kind: PropertyKind::Primitive,
                    required: $property->required(),
                    type: \is_string($declaredType) ? $declaredType : null,
                    default: $propertyType->default(),
                );

                continue;
            }

            $resolutions[] = $this->resolveReference($key, $declaredType, $property->required(), $context, $storedRequirements[$key] ?? null);
        }

        return $resolutions;
    }

    private function resolveReference(string $key, string $fqcn, bool $required, ResolutionContext $context, ?DataRequirement $storedRequirement): PropertyResolution
    {
        $contextOffers = $this->contextCandidates($fqcn, $context);
        $loaders = $this->loaderCandidates($fqcn);
        $stored = $this->storedCandidate($fqcn, $storedRequirement);

        return new PropertyResolution(
            key: $key,
            kind: PropertyKind::Reference,
            required: $required,
            fqcn: $fqcn,
            resolved: $stored ?? $this->pickDefault($contextOffers, $loaders),
            candidates: [...$contextOffers, ...$loaders],
        );
    }

    /**
     * Applied wiring is a resolution, not a candidate: a Stored requirement whose produced type resolves and
     * is assignable to the declared FQCN becomes the resolved pick directly, never a candidates menu entry.
     * A config that fails to resolve (client defect) or resolves to a mismatched type yields no Stored
     * candidate — {@see LayoutDiagnostics} reports the former as InvalidConfig, the latter as
     * MismatchedReferenceType.
     */
    private function storedCandidate(string $fqcn, ?DataRequirement $requirement): ?ResolutionCandidate
    {
        if ($requirement === null) {
            return null;
        }

        try {
            $produced = $this->dataLoaderProvider->get($requirement->source)->resolveProducedType($requirement->config);
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            return null;
        }

        if (!is_a($produced, $fqcn, true)) {
            return null;
        }

        return new ResolutionCandidate(origin: CandidateOrigin::Stored);
    }

    /**
     * Every available context entry whose FQCN fits, minted on the type match alone and blind to whether the
     * element carries wiring for it: a candidate is an offer the writers below and any authoring UI act on, and
     * gating it on existing wiring would hide the offer exactly where wiring does not exist yet. The entry's
     * {@see ProvidedContext::$root} flag decides the origin, and both origins land in the one candidates list.
     *
     * @return list<ResolutionCandidate>
     */
    private function contextCandidates(string $fqcn, ResolutionContext $context): array
    {
        $candidates = [];

        foreach ($context->available as $provided) {
            if (!is_a($provided->fqcn, $fqcn, true)) {
                continue;
            }

            $candidates[] = new ResolutionCandidate(
                origin: $provided->root ? CandidateOrigin::Root : CandidateOrigin::Parent,
                contextKey: $provided->contextKey,
                providerElementId: $provided->providerElementId,
                path: $provided->path,
                distribution: $provided->distribution,
                contextType: $provided->contextType,
            );
        }

        return $candidates;
    }

    /**
     * @return list<ResolutionCandidate>
     */
    private function loaderCandidates(string $fqcn): array
    {
        $map = $this->mapResolver->resolve();

        $candidates = [];

        foreach ($map->getSourcesFor($fqcn) as $source) {
            $capability = $map->capabilityFor($source, $fqcn);

            if ($capability === null) {
                continue;
            }

            $candidates[] = new ResolutionCandidate(
                origin: CandidateOrigin::Loader,
                loaderSource: $source,
                configTemplate: $capability->configTemplate,
                configComplete: $this->isConfigComplete($source, $capability, $map),
            );
        }

        return $candidates;
    }

    private function isConfigComplete(string $source, LoaderTypeCapability $capability, ContentSystemDataLoaderMap $map): bool
    {
        if ($map->residualConfigKeysFor($source, $capability) !== []) {
            return false;
        }

        try {
            $this->configSerializers->decode($source, $capability->configTemplate);
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            return false;
        }

        return true;
    }

    /**
     * Deterministic, conservative default selection over three ordered pools. Root outranks Parent: the page's
     * root context is the more relevant source in the default storefront layouts. Ambiguity inside the
     * preferred pool is still ambiguity, so two Root candidates return null rather than falling through to a
     * lone Parent candidate; `wire-context` is the deliberate path out of that. Returns null when no source is
     * unambiguously correct; the diagnostics layer turns that into ambiguous_required / unresolved_required for
     * required properties.
     *
     * @param list<ResolutionCandidate> $contextOffers Root and Parent candidates, in availability order
     * @param list<ResolutionCandidate> $loaders
     */
    private function pickDefault(array $contextOffers, array $loaders): ?ResolutionCandidate
    {
        $roots = $this->withOrigin($contextOffers, CandidateOrigin::Root);

        if ($roots !== []) {
            return \count($roots) === 1 ? $roots[0] : null;
        }

        $parents = $this->withOrigin($contextOffers, CandidateOrigin::Parent);

        if ($parents !== []) {
            return \count($parents) === 1 ? $parents[0] : null;
        }

        $completeLoaders = array_values(array_filter(
            $loaders,
            static fn (ResolutionCandidate $candidate): bool => $candidate->configComplete,
        ));

        return \count($completeLoaders) === 1 ? $completeLoaders[0] : null;
    }

    /**
     * @param list<ResolutionCandidate> $candidates
     *
     * @return list<ResolutionCandidate>
     */
    private function withOrigin(array $candidates, CandidateOrigin $origin): array
    {
        return array_values(array_filter(
            $candidates,
            static fn (ResolutionCandidate $candidate): bool => $candidate->origin === $origin,
        ));
    }
}
