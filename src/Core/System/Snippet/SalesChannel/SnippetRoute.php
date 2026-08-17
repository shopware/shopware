<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Snippet\SnippetException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\MessageCatalogueInterface;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class SnippetRoute extends AbstractSnippetRoute
{
    final public const MAX_PREFIXES = 50;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractTranslator $translator,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        private readonly Connection $connection,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    #[Route(
        path: '/store-api/snippet',
        name: 'store-api.snippet',
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, SalesChannelContext $context): SnippetRouteResponse
    {
        $prefixes = $this->normalizePrefixes($this->parseList($request->query->getString('prefixes')));
        if (\count($prefixes) > self::MAX_PREFIXES) {
            throw SnippetException::tooManyPrefixes(\count($prefixes), self::MAX_PREFIXES);
        }

        $languageIds = $this->parseList($request->query->getString('languageIds'));
        $languageIds = array_values(array_unique(array_map('mb_strtolower', $languageIds)));
        sort($languageIds);

        if ($languageIds !== []) {
            $this->validateLanguages($languageIds, $context->getSalesChannelId());
        } else {
            $languageIds = [$context->getLanguageId()];
        }

        $results = [];
        foreach ($languageIds as $languageId) {
            $results[] = $this->loadForLanguage($languageId, $prefixes, $context);
        }

        $response = new SnippetRouteResponse(new SnippetSetResultList($results));

        $hashes = array_map(static fn (SnippetSetResult $result): string => $result->hash, $results);
        // a single set keeps its own hash as etag, multiple sets are combined into one
        $etag = \count($hashes) === 1 ? implode('', $hashes) : Hasher::hash(implode('-', $hashes));

        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }

    public function getDecorated(): AbstractSnippetRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param list<string> $prefixes
     */
    private function loadForLanguage(string $languageId, array $prefixes, SalesChannelContext $context): SnippetSetResult
    {
        $locale = $this->languageLocaleProvider->getLocaleForLanguageId($languageId);

        $this->translator->injectSettings($context->getSalesChannelId(), $languageId, $locale, $context->getContext());

        try {
            $catalogue = $this->translator->getCatalogue($locale);
            $snippetSetId = $this->translator->getSnippetSetId($locale);
        } finally {
            $this->translator->resetInjection();
        }

        $this->cacheTagCollector->addTag(Translator::tag($snippetSetId));

        $snippets = $this->flattenCatalogue($catalogue);

        if ($prefixes !== []) {
            $snippets = array_filter(
                $snippets,
                static fn (string|int $key): bool => self::matchesAnyPrefix((string) $key, $prefixes),
                \ARRAY_FILTER_USE_KEY
            );
        }

        ksort($snippets);

        $fallbackLocale = explode('-', $locale)[0];

        return new SnippetSetResult(
            languageId: $languageId,
            locale: $locale,
            fallbackLocale: $fallbackLocale !== $locale ? $fallbackLocale : null,
            snippetSetId: $snippetSetId,
            hash: Hasher::hash($snippets),
            snippets: $snippets,
        );
    }

    /**
     * Collects the messages of the whole catalogue fallback chain, most specific locale wins
     *
     * @return array<string, string>
     */
    private function flattenCatalogue(MessageCatalogueInterface $catalogue): array
    {
        $catalogues = [];
        $current = $catalogue;
        while ($current !== null) {
            $catalogues[] = $current;
            $current = $current->getFallbackCatalogue();
        }

        $snippets = [];
        foreach (array_reverse($catalogues) as $chainCatalogue) {
            foreach ($chainCatalogue->all('messages') as $key => $value) {
                if (\is_string($value)) {
                    $snippets[(string) $key] = $value;
                }
            }
        }

        return $snippets;
    }

    /**
     * @return list<string>
     */
    private function parseList(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $item): bool => $item !== ''
        ));
    }

    /**
     * Prefixes are matched on namespace segments, so a trailing dot is optional: `checkout` and `checkout.`
     * both match `checkout.cart.title` but never `checkoutConfirm.title`
     *
     * @param list<string> $prefixes
     *
     * @return list<string>
     */
    private function normalizePrefixes(array $prefixes): array
    {
        $prefixes = array_values(array_unique(array_filter(
            array_map(static fn (string $prefix): string => rtrim($prefix, '.'), $prefixes),
            static fn (string $prefix): bool => $prefix !== ''
        )));

        sort($prefixes);

        return $prefixes;
    }

    /**
     * @param list<string> $prefixes
     */
    private static function matchesAnyPrefix(string $key, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($key === $prefix || str_starts_with($key, $prefix . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $languageIds
     */
    private function validateLanguages(array $languageIds, string $salesChannelId): void
    {
        foreach ($languageIds as $languageId) {
            if (!Uuid::isValid($languageId)) {
                throw SnippetException::languageNotAvailableInSalesChannel($languageId, $salesChannelId);
            }
        }

        /** @var list<string> $availableLanguageIds */
        $availableLanguageIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(`language_id`)) FROM `sales_channel_language` WHERE `sales_channel_id` = :salesChannelId',
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]
        );

        foreach ($languageIds as $languageId) {
            if (!\in_array($languageId, $availableLanguageIds, true)) {
                throw SnippetException::languageNotAvailableInSalesChannel($languageId, $salesChannelId);
            }
        }
    }
}
