<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\SalesChannel;

use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Snippet\SnippetException;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * Loads the fully resolved snippets (translations) of a sales channel context per language,
 * reusing the translator's snippet set resolution, language fallback and theme filtering.
 *
 * @internal
 */
#[Package('discovery')]
class SalesChannelSnippetLoader
{
    final public const MAX_PREFIXES = 50;

    /**
     * @param SalesChannelRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly AbstractTranslator $translator,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        private readonly SalesChannelRepository $languageRepository,
    ) {
    }

    /**
     * @param list<string> $languageIds language ids to load, an empty list loads the context language
     * @param list<string> $prefixes namespace prefixes to limit the result, an empty list loads everything
     *
     * @return list<SnippetSetResult>
     */
    public function load(array $languageIds, array $prefixes, SalesChannelContext $context): array
    {
        $prefixes = $this->normalizePrefixes($prefixes);
        if (\count($prefixes) > self::MAX_PREFIXES) {
            throw SnippetException::tooManyPrefixes(\count($prefixes), self::MAX_PREFIXES);
        }

        $languageIds = array_values(array_unique(array_map('mb_strtolower', $languageIds)));
        sort($languageIds);

        if ($languageIds !== []) {
            $this->validateLanguages($languageIds, $context);
        } else {
            $languageIds = [$context->getLanguageId()];
        }

        $results = [];
        foreach ($languageIds as $index => $languageId) {
            if ($index > 0) {
                // reset the translator's per-locale memoisation, otherwise languages sharing one locale would reuse the first language's snippet set
                $this->translator->reset();
            }

            $results[] = $this->loadForLanguage($languageId, $prefixes, $context);
        }

        return $results;
    }

    /**
     * @param list<string> $prefixes
     */
    private function loadForLanguage(string $languageId, array $prefixes, SalesChannelContext $context): SnippetSetResult
    {
        $locale = $this->languageLocaleProvider->getLocaleForLanguageId($languageId);

        try {
            // injectSettings() mutates the translator's locale before it resolves the snippet set,
            // it belongs inside the try so a database error still triggers the resetInjection()
            $this->translator->injectSettings($context->getSalesChannelId(), $languageId, $locale, $context->getContext());

            $catalogue = $this->translator->getCatalogue($locale);
            $snippetSetId = $this->translator->getSnippetSetId($locale);
        } finally {
            $this->translator->resetInjection();
        }

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
    private function validateLanguages(array $languageIds, SalesChannelContext $context): void
    {
        foreach ($languageIds as $languageId) {
            if (!Uuid::isValid($languageId)) {
                throw SnippetException::languageNotAvailableInSalesChannel($languageId, $context->getSalesChannelId());
            }
        }

        $criteria = new Criteria($languageIds);
        $criteria->setTitle('snippet-loader::validate-languages');

        // the sales channel language definition restricts the search to the languages assigned to the context's sales channel
        $availableLanguageIds = $this->languageRepository->searchIds($criteria, $context)->getIds();

        foreach ($languageIds as $languageId) {
            if (!\in_array($languageId, $availableLanguageIds, true)) {
                throw SnippetException::languageNotAvailableInSalesChannel($languageId, $context->getSalesChannelId());
            }
        }
    }
}
