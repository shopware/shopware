<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Resolution;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * The per-element resolution kernel. For each declared property of the element's type it determines how the
 * property could be filled at its position: primitives carry a static value; references collect candidate
 * sources (ancestor/root providers plus data loaders) and a deterministic conservative default selection.
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
        private readonly AbstractContentSystemDataLoaderTypeResolver $typeResolver,
        private readonly DataLoaderConfigSerializerProvider $configSerializers,
    ) {
    }

    /**
     * @return list<PropertyResolution>
     */
    public function resolve(ContentElement|string $element, ResolutionContext $context): array
    {
        $type = \is_string($element) ? $element : $element->getComponent();

        if (!$this->registry->has($type)) {
            return [];
        }

        $resolutions = [];

        foreach ($this->registry->get($type)->properties() as $key => $property) {
            $propertyType = $property->type();

            if ($propertyType->isPrimitive()) {
                $resolutions[] = new PropertyResolution(
                    key: $key,
                    kind: PropertyKind::Primitive,
                    required: $property->required(),
                    type: $propertyType->type(),
                    default: $propertyType->default(),
                );

                continue;
            }

            $resolutions[] = $this->resolveReference($key, $propertyType->type(), $property->required(), $context);
        }

        return $resolutions;
    }

    private function resolveReference(string $key, string $fqcn, bool $required, ResolutionContext $context): PropertyResolution
    {
        $parents = $this->parentCandidates($fqcn, $context);
        $loaders = $this->loaderCandidates($fqcn);

        return new PropertyResolution(
            key: $key,
            kind: PropertyKind::Reference,
            required: $required,
            fqcn: $fqcn,
            resolved: $this->pickDefault($parents, $loaders),
            candidates: [...$parents, ...$loaders],
        );
    }

    /**
     * @return list<ResolutionCandidate>
     */
    private function parentCandidates(string $fqcn, ResolutionContext $context): array
    {
        $candidates = [];

        foreach ($context->available as $provided) {
            if (!is_a($provided->fqcn, $fqcn, true)) {
                continue;
            }

            $candidates[] = new ResolutionCandidate(
                origin: CandidateOrigin::Parent,
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
        $map = $this->typeResolver->resolve();

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
                configComplete: $this->isConfigComplete($source, $capability),
            );
        }

        return $candidates;
    }

    private function isConfigComplete(string $source, LoaderTypeCapability $capability): bool
    {
        if ($capability->requiredConfigKeys !== []) {
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
     * Deterministic, conservative default selection. Returns null when no source is unambiguously correct;
     * the diagnostics layer turns that into ambiguous_required / unresolved_required for required properties.
     *
     * @param list<ResolutionCandidate> $parents
     * @param list<ResolutionCandidate> $loaders
     */
    private function pickDefault(array $parents, array $loaders): ?ResolutionCandidate
    {
        if (\count($parents) === 1) {
            return $parents[0];
        }

        if ($parents !== []) {
            return null;
        }

        $completeLoaders = array_values(array_filter(
            $loaders,
            static fn (ResolutionCandidate $candidate): bool => $candidate->configComplete,
        ));

        if (\count($completeLoaders) === 1) {
            return $completeLoaders[0];
        }

        return null;
    }
}
