<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * The layout-wide diagnostics model. Runs the resolution kernel for every element against its position and
 * adds cross-element checks, producing a {@see LayoutAnalysis} that carries both the per-element resolutions
 * and a diagnostics report addressed by element id plus property/context key.
 *
 * The binding-scope checks (required-property satisfaction, broken chains) run only when a bound source's
 * root context is supplied; with a null root context only the well-formedness subset runs. The analysis
 * never reads sales-channel state — a plain {@see Context} suffices.
 */
#[Package('framework')]
class LayoutDiagnostics
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AvailableContextResolver $availableContextResolver,
        private readonly ElementResolver $elementResolver,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly RootContextMapper $rootContextMapper,
    ) {
    }

    /**
     * @param list<ContentElement> $tree
     * @param list<ProvidedContext>|null $rootContext the bound source's root-ambient context, or null for the well-formedness subset
     */
    public function analyze(array $tree, ?array $rootContext, ?Context $context = null): LayoutAnalysis
    {
        $elements = $this->flatten($tree);

        $violations = [];
        $resolutions = [];

        foreach ($this->duplicateIdViolations($elements) as $violation) {
            $violations[] = $violation;
        }

        foreach ($elements as $element) {
            foreach ($this->intrinsicElementViolations($element) as $violation) {
                $violations[] = $violation;
            }

            $available = $this->availableContextResolver->resolve($element->getId(), $tree, $rootContext ?? []);
            $elementResolutions = $this->elementResolver->resolve($element, new ResolutionContext($element->getId(), $available));
            $resolutions[$element->getId()] = $elementResolutions;

            if ($rootContext === null) {
                continue;
            }

            foreach ($this->bindingViolations($element, $elementResolutions, $available) as $violation) {
                $violations[] = $violation;
            }
        }

        return new LayoutAnalysis(new DiagnosticsReport($violations), $resolutions);
    }

    /**
     * @param list<ContentElement> $elements
     *
     * @return list<Violation>
     */
    private function duplicateIdViolations(array $elements): array
    {
        $counts = [];
        foreach ($elements as $element) {
            $counts[$element->getId()] = ($counts[$element->getId()] ?? 0) + 1;
        }

        $violations = [];
        foreach ($counts as $id => $count) {
            if ($count < 2) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::DuplicateElementId,
                (string) $id,
                null,
                \sprintf('Element id "%s" is not unique across the layout.', $id),
            );
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function intrinsicElementViolations(ContentElement $element): array
    {
        $violations = [];

        if (!$this->registry->has($element->getComponent())) {
            $violations[] = new Violation(
                ViolationCode::UnregisteredComponent,
                $element->getId(),
                null,
                \sprintf('Component "%s" is not a registered element type.', $element->getComponent()),
            );
        }

        foreach ($element->getDataRequirements() as $key => $requirement) {
            $invalidConfig = $this->invalidConfigViolation($element->getId(), (string) $key, $requirement);

            if ($invalidConfig !== null) {
                $violations[] = $invalidConfig;
            }
        }

        foreach ($this->orphanedProviderViolations($element) as $violation) {
            $violations[] = $violation;
        }

        return $violations;
    }

    private function invalidConfigViolation(string $elementId, string $key, DataRequirement $requirement): ?Violation
    {
        try {
            $this->rootContextMapper->resolveType($requirement);
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            return new Violation(ViolationCode::InvalidConfig, $elementId, $key, $exception->getMessage());
        }

        return null;
    }

    /**
     * A provider whose key is consumed by no descendant element is orphaned (warning, non-blocking).
     *
     * @return list<Violation>
     */
    private function orphanedProviderViolations(ContentElement $element): array
    {
        $providers = $element->getProvidesContext();

        if ($providers === []) {
            return [];
        }

        $consumedKeys = [];
        foreach ($this->flatten([...$element->allSlotElements()]) as $descendant) {
            foreach ($descendant->getAcceptsContext() as $consumerKey => $consumer) {
                $consumedKeys[$consumerKey] = true;
            }
        }

        $violations = [];
        foreach ($providers as $providerKey => $provider) {
            if (isset($consumedKeys[$providerKey])) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::OrphanedProvider,
                $element->getId(),
                (string) $providerKey,
                \sprintf('Provider "%s" has no consumer in scope.', $providerKey),
            );
        }

        return $violations;
    }

    /**
     * @param list<PropertyResolution> $resolutions
     * @param list<ProvidedContext> $available
     *
     * @return list<Violation>
     */
    private function bindingViolations(ContentElement $element, array $resolutions, array $available): array
    {
        $violations = [];

        foreach ($resolutions as $resolution) {
            $violation = $this->propertyBindingViolation($element->getId(), $resolution);

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        return [...$violations, ...$this->brokenChainViolations($element, $available)];
    }

    private function propertyBindingViolation(string $elementId, PropertyResolution $resolution): ?Violation
    {
        if ($resolution->kind === PropertyKind::Primitive) {
            if ($resolution->required && $resolution->default === null) {
                return new Violation(
                    ViolationCode::UnresolvedRequired,
                    $elementId,
                    $resolution->key,
                    \sprintf('Required property "%s" has no value.', $resolution->key),
                );
            }

            return null;
        }

        if ($resolution->resolved !== null) {
            return null;
        }

        if ($resolution->required) {
            $code = $this->usableCandidateCount($resolution->candidates) >= 2 ? ViolationCode::AmbiguousRequired : ViolationCode::UnresolvedRequired;

            return new Violation(
                $code,
                $elementId,
                $resolution->key,
                \sprintf('Required property "%s" is not deterministically resolvable.', $resolution->key),
                $resolution->candidates,
            );
        }

        if ($resolution->candidates === []) {
            return new Violation(
                ViolationCode::UnresolvedOptional,
                $elementId,
                $resolution->key,
                \sprintf('Optional property "%s" has no source.', $resolution->key),
            );
        }

        return null;
    }

    /**
     * Candidates the resolver could actually select: a parent (received-context) provider, or a loader whose
     * config is complete. Incomplete loaders cannot be picked, so 0 parents + N incomplete loaders is
     * unresolved, not ambiguous.
     *
     * @param list<ResolutionCandidate> $candidates
     */
    private function usableCandidateCount(array $candidates): int
    {
        $usable = array_filter(
            $candidates,
            static fn (ResolutionCandidate $candidate): bool => match ($candidate->origin) {
                CandidateOrigin::Parent => true,
                CandidateOrigin::Loader => $candidate->configComplete,
            },
        );

        return \count($usable);
    }

    /**
     * @param list<ProvidedContext> $available
     *
     * @return list<Violation>
     */
    private function brokenChainViolations(ContentElement $element, array $available): array
    {
        $availableKeys = [];
        foreach ($available as $provided) {
            $availableKeys[$provided->contextKey] = true;
        }

        $violations = [];
        foreach ($element->getAcceptsContext() as $consumerKey => $consumer) {
            if (!$consumer->required || isset($availableKeys[$consumerKey])) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::BrokenRequiredChain,
                $element->getId(),
                (string) $consumerKey,
                \sprintf('Required context "%s" is provided by no ancestor or bound source.', $consumerKey),
            );
        }

        return $violations;
    }

    /**
     * @param array<ContentElement> $tree
     *
     * @return list<ContentElement>
     */
    private function flatten(array $tree): array
    {
        $elements = [];

        foreach ($tree as $element) {
            $elements[] = $element;

            foreach ($this->flatten([...$element->allSlotElements()]) as $descendant) {
                $elements[] = $descendant;
            }
        }

        return $elements;
    }
}
