<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Registry;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Aggregator over the tagged providers with a request-lifetime catalogue cache.
 *
 * Candidate display names are unique across all providers for a root source. A collision is a configuration
 * error because a display name cannot distinguish candidates that carry different source references.
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
    public function __construct(
        private readonly iterable $providers,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
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
    ): MappingSourceResolution {
        if ($context->rootSource === null) {
            return MappingSourceResolution::noValue();
        }

        $catalogue = $this->catalogueForRootSource($context->rootSource);
        $candidateKey = $source->displayName();
        $candidate = $catalogue['candidates'][$candidateKey] ?? null;
        $provider = $catalogue['providers'][$candidateKey] ?? null;

        if (
            $candidate === null
            || $provider === null
            || !$candidate->source->isSameAs($source)
        ) {
            return MappingSourceResolution::noValue();
        }

        $context->cacheContext?->addTags($candidate->cacheTags);

        try {
            if ($source->type !== 'root' && !$provider->supportsSource($source)) {
                return MappingSourceResolution::noValue();
            }

            $result = $source->type === 'root'
                ? $provider->resolveRootSource($source, $context)
                : $provider->resolveSource($source, $context);
        } catch (\Throwable $failure) {
            return $this->logProviderFailure($provider, $candidateKey, $context, $failure);
        }

        if (!$result->isFailure() || $result->failure === null) {
            return $result;
        }

        return $this->logProviderFailure($provider, $candidateKey, $context, $result->failure);
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
                    throw ContentSystemException::duplicateMappingCandidate($candidateKey);
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

    private function logProviderFailure(
        AbstractMappingCandidateProvider $provider,
        string $candidate,
        MappingSourceResolutionContext $context,
        \Throwable $failure,
    ): MappingSourceResolution {
        $this->logger->error('A content mapping provider failed while resolving a candidate.', [
            'exception' => $failure,
            'provider' => $provider::class,
            'rootSource' => $context->rootSource,
            'candidate' => $candidate,
            'elementId' => $context->element->id,
        ]);

        return MappingSourceResolution::failed($failure);
    }
}
