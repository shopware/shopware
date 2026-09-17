<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Registry;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Stateless aggregator over the tagged providers.
 *
 * Two providers offering the same path is not an error — a cross-cutting provider legitimately overlaps an
 * entity's own — so the first provider to offer a path keeps it and a later one does not displace it. Provider
 * order is the tag's registration order, which puts the more specific provider first when it declares the
 * higher priority.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemMappingCandidateRegistry extends AbstractContentSystemMappingCandidateRegistry
{
    /**
     * @param iterable<AbstractMappingCandidateProvider> $providers
     */
    public function __construct(private readonly iterable $providers)
    {
    }

    public function getDecorated(): AbstractContentSystemMappingCandidateRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    public function forRootSource(string $rootSource): array
    {
        $candidates = [];

        foreach ($this->providers as $provider) {
            if (!$provider->supports($rootSource)) {
                continue;
            }

            foreach ($provider->provide($rootSource) as $candidate) {
                if (isset($candidates[$candidate->path])) {
                    continue;
                }

                $candidates[$candidate->path] = $candidate;
            }
        }

        return $candidates;
    }
}
