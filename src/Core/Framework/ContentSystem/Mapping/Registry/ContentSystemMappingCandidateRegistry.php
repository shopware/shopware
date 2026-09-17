<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Registry;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Aggregator over the tagged providers with a request-lifetime catalogue cache.
 *
 * Two providers offering the same typed source reference is not an error — a cross-cutting provider can
 * overlap an entity provider — so the first provider to offer the reference keeps it. Provider order is the
 * tag's registration order, which puts the more specific provider first when it declares the higher priority.
 * The candidate and provider index is reused for each root source during a request, then cleared by `kernel.reset`
 * so dynamic provider data such as custom fields is refreshed on the next request.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemMappingCandidateRegistry extends AbstractContentSystemMappingCandidateRegistry implements ResetInterface
{
    /**
     * @var array<string, array{
     *     candidates: array<string, MappingCandidate>,
     *     providers: array<string, AbstractMappingCandidateProvider>
     * }>
     */
    private array $catalogues = [];

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

    public function reset(): void
    {
        $this->catalogues = [];
    }

    public function resolveSource(
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): mixed {
        if ($context->rootSource !== null) {
            $catalogue = $this->catalogueForRootSource($context->rootSource);
            $candidateKey = $source->displayName();
            $candidate = $catalogue['candidates'][$candidateKey] ?? null;
            $provider = $catalogue['providers'][$candidateKey] ?? null;

            if (
                $candidate === null
                || $provider === null
                || !$candidate->source->isSameAs($source)
                || !$provider->supportsSource($source)
            ) {
                return null;
            }

            return $provider->resolveSource($source, $context);
        }

        foreach ($this->providers as $provider) {
            if ($provider->supportsSource($source)) {
                return $provider->resolveSource($source, $context);
            }
        }

        return null;
    }

    public function forRootSource(string $rootSource): array
    {
        return $this->catalogueForRootSource($rootSource)['candidates'];
    }

    /**
     * @return array{
     *     candidates: array<string, MappingCandidate>,
     *     providers: array<string, AbstractMappingCandidateProvider>
     * }
     */
    private function catalogueForRootSource(string $rootSource): array
    {
        return $this->catalogues[$rootSource] ??= $this->buildCatalogueForRootSource($rootSource);
    }

    /**
     * @return array{
     *     candidates: array<string, MappingCandidate>,
     *     providers: array<string, AbstractMappingCandidateProvider>
     * }
     */
    private function buildCatalogueForRootSource(string $rootSource): array
    {
        $candidates = [];
        $providers = [];

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
                $providers[$candidateKey] = $provider;
            }
        }

        return [
            'candidates' => $candidates,
            'providers' => $providers,
        ];
    }
}
