<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Registry;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Stateless aggregator over the tagged providers.
 *
 * Two providers offering the same typed source reference is not an error — a cross-cutting provider can
 * overlap an entity provider — so the first provider to offer the reference keeps it. Provider order is the
 * tag's registration order, which puts the more specific provider first when it declares the higher priority.
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

    public function resolveSource(
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): mixed {
        foreach ($this->providers as $provider) {
            if ($provider->supportsSource($source)) {
                return $provider->resolveSource($source, $context);
            }
        }

        return null;
    }

    public function forRootSource(string $rootSource): array
    {
        $candidates = [];

        foreach ($this->providers as $provider) {
            if (!$provider->supports($rootSource)) {
                continue;
            }

            foreach ($provider->provide($rootSource) as $candidate) {
                $candidateKey = $candidate->source->displayName();
                if (isset($candidates[$candidateKey])) {
                    continue;
                }

                $candidates[$candidateKey] = $candidate;
            }
        }

        return $candidates;
    }
}
