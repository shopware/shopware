<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Resolves a root-source id to its specification source and root-ambient context.
 *
 * Entity-type ids and section ids must remain disjoint: knownRootSources() flattens them into one namespace and
 * sourceFor() probes the entity branch before the section locator, so a collision would silently resolve to the
 * entity source. This holds today (sections are header/footer; entity types are DAL entity names).
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class RootSourceRegistry
{
    /**
     * @param list<string> $entityTypes the entity-type ids baked at build time by ContentLayoutAssignableCompilerPass
     * @param ServiceLocator<AbstractSpecificationSource> $sectionSources
     * @param iterable<AbstractSpecificationSource> $entitySources
     */
    public function __construct(
        private readonly array $entityTypes,
        private readonly ServiceLocator $sectionSources,
        private readonly NoneSpecificationSource $noneSource,
        private readonly iterable $entitySources,
    ) {
    }

    /**
     * The union of entity-type ids, section ids, and "none". Sections come from the locator keys (not
     * ContentSection::cases()) so MAIN — which has no section source — never appears as a valid root source.
     *
     * @return list<string>
     */
    public function knownRootSources(): array
    {
        return array_values([
            ...array_unique($this->entityTypes),
            ...array_keys($this->sectionSources->getProvidedServices()),
            NoneSpecificationSource::ROOT_SOURCE,
        ]);
    }

    /**
     * The entity-type subset, for the /api/_info/content-system-entity-types.json picker (which stays entity-only;
     * header/footer/none are not entity types).
     *
     * @return list<string>
     */
    public function entityRootSources(): array
    {
        return array_values(array_unique($this->entityTypes));
    }

    /**
     * The root-ambient context the source supplies, [] for "none" / header / footer. Fail-hard on an id not in
     * knownRootSources() (it never returns [] for an unknown id, which would be silent degradation); callers gate
     * membership first.
     *
     * @return list<ProvidedContext>
     */
    public function resolve(string $rootSource, Context $context): array
    {
        return $this->sourceFor($rootSource)->providedRootContext($context);
    }

    /**
     * Membership-gated resolve shared by the diagnose and draft-mutation routes: null/empty → no bound source
     * (intrinsic-only); a non-member → unknownRootSource 400; a member → its root-ambient context. This is the
     * membership gate resolve()'s docblock pushes onto callers, hosted once so the routes no longer duplicate it.
     *
     * @return list<ProvidedContext>|null
     */
    public function resolveGated(?string $rootSource, Context $context): ?array
    {
        if ($rootSource === null || $rootSource === '') {
            return null;
        }

        if (!\in_array($rootSource, $this->knownRootSources(), true)) {
            throw ContentSystemException::unknownRootSource($rootSource);
        }

        return $this->resolve($rootSource, $context);
    }

    public function sourceFor(string $rootSource): AbstractSpecificationSource
    {
        if ($rootSource === NoneSpecificationSource::ROOT_SOURCE) {
            return $this->noneSource;
        }

        if (\in_array($rootSource, $this->entityTypes, true)) {
            foreach ($this->entitySources as $source) {
                if ($source->supportsEntityType($rootSource)) {
                    return $source;
                }
            }
        }

        if ($this->sectionSources->has($rootSource)) {
            return $this->sectionSources->get($rootSource);
        }

        throw ContentSystemException::rootSourceResolutionUnsupported($rootSource);
    }
}
