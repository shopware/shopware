<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Registry;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Aggregator over the tagged providers with a request-lifetime catalogue cache.
 *
 * Candidate display names are unique across all providers for a root source. Runtime collisions exclude all
 * candidates with the ambiguous name and are logged once per name and catalogue build.
 * The candidate and provider index is reused for each root source during a request, then cleared by `kernel.reset`
 * so dynamic provider data such as custom fields is refreshed on the next request.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemMappingCandidateRegistry extends AbstractContentSystemMappingCandidateRegistry
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
        private readonly LoggerInterface $logger,
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

    public function forRootSource(string $rootSource): array
    {
        return $this->catalogueForRootSource($rootSource)['candidates'];
    }

    public function cacheTagsForMapping(string $rootSource, string $path): array
    {
        $tags = [];
        foreach ($this->providers as $provider) {
            if ($provider->supports($rootSource)) {
                $tags[] = $provider->cacheTagsForMapping($rootSource, $path);
            }
        }

        return array_values(array_unique(array_merge(...$tags)));
    }

    protected function resolveAdmittedSource(
        string $rootSource,
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): MappingSourceResolution {
        $provider = $this->catalogueForRootSource($rootSource)['providers'][$source->displayName()] ?? null;
        if ($provider === null) {
            return MappingSourceResolution::noValue();
        }

        return $source->type === 'root'
            ? $provider->resolveRootSource($source, $context)
            : $provider->resolveSource($source, $context);
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
        $ambiguous = [];

        foreach ($this->providers as $provider) {
            if (!$provider->supports($rootSource)) {
                continue;
            }

            foreach ($provider->provide($rootSource) as $candidate) {
                $candidateKey = $candidate->source->displayName();
                if (isset($ambiguous[$candidateKey])) {
                    continue;
                }

                if (isset($candidates[$candidateKey])) {
                    $this->logger->warning('Ambiguous mapping candidates were excluded from the catalogue.', [
                        'rootSource' => $rootSource,
                        'candidate' => $candidateKey,
                        'providers' => [$providers[$candidateKey]::class, $provider::class],
                    ]);
                    unset($candidates[$candidateKey], $providers[$candidateKey]);
                    $ambiguous[$candidateKey] = true;

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
