<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Intl\Locale;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\Translator as SymfonyTranslator;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

#[Package('core')]
class Translator extends AbstractTranslator
{
    use TranslatorTrait;

    /**
     * @var array<string, MessageCatalogueInterface>
     */
    private array $isCustomized = [];

    private ?string $snippetSetId = null;

    private ?string $salesChannelId = null;

    private ?string $localeBeforeInject = null;

    /**
     * @var array<string, bool>
     */
    private array $keys = ['all' => true];

    /**
     * @var array<string, array<string, bool>>
     */
    private array $traces = [];

    /**
     * @var array<string, string>
     */
    private array $snippets = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly CacheInterface $cache,
        private readonly MessageFormatterInterface $formatter,
        private readonly string $environment,
        private readonly Connection $connection,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        private readonly SnippetService $snippetService,
        private readonly bool $fineGrainedCache
    ) {
    }

    public static function buildName(string $id): string
    {
        if (\strpbrk($id, (string) ItemInterface::RESERVED_CHARACTERS) !== false) {
            $id = \str_replace(\str_split((string) ItemInterface::RESERVED_CHARACTERS, 1), '_r_', $id);
        }

        return 'translator.' . $id;
    }

    public function getDecorated(): AbstractTranslator
    {
        throw new DecorationPatternException(self::class);
    }

    public function trace(string $key, \Closure $param)
    {
        $this->traces[$key] = [];
        $this->keys[$key] = true;

        $result = $param();

        unset($this->keys[$key]);

        return $result;
    }

    public function getTrace(string $key): array
    {
        $trace = isset($this->traces[$key]) ? array_keys($this->traces[$key]) : [];
        unset($this->traces[$key]);

        return $trace;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        $catalog = $this->translator->getCatalogue($locale);

        $fallbackLocale = $this->getFallbackLocale();

        $localization = mb_substr($fallbackLocale, 0, 2);
        if ($this->isShopwareLocaleCatalogue($catalog) && !$this->isFallbackLocaleCatalogue($catalog, $localization)) {
            $catalog->addFallbackCatalogue($this->translator->getCatalogue($localization));
        } else {
            // fallback locale and current locale has the same localization -> reset fallback
            // or locale is symfony style locale so we shouldn't add shopware fallbacks as it may lead to circular references
            $fallbackLocale = null;
        }

        // disable fallback logic to display symfony warnings
        if ($this->environment !== 'prod') {
            $fallbackLocale = null;
        }

        return $this->getCustomizedCatalog($catalog, $fallbackLocale);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if ($domain === null) {
            $domain = 'messages';
        }

        if ($this->fineGrainedCache) {
            foreach (array_keys($this->keys) as $trace) {
                $this->traces[$trace][self::buildName($id)] = true;
            }
        } else {
            foreach (array_keys($this->keys) as $trace) {
                $this->traces[$trace]['shopware.translator'] = true;
            }
        }

        $catalogue = $this->getCatalogue($locale);

        // the formatter expects 2 char locale or underscore locales, `Locale::getFallback()` transforms the codes
        // We use the locale from the catalogue here as that may be the fallback locale,
        // so we always format the translations in the actual locale of the catalogue
        $formatLocale = Locale::getFallback($catalogue->getLocale()) ?? $catalogue->getLocale();

        return $this->formatter->format($catalogue->get($id, $domain), $formatLocale, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale(string $locale): void
    {
        $this->translator->setLocale($locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    public function warmUp($cacheDir): void
    {
        if ($this->translator instanceof WarmableInterface) {
            $this->translator->warmUp($cacheDir);
        }
    }

    public function reset(): void
    {
        $this->resetInjection();

        $this->isCustomized = [];
        $this->snippets = [];
        $this->traces = [];
        $this->keys = ['all' => true];
        $this->snippetSetId = null;
        $this->salesChannelId = null;
        $this->localeBeforeInject = null;
        $this->locale = null;
        if ($this->translator instanceof SymfonyTranslator) {
            // Reset FallbackLocale in memory cache of symfony implementation
            // set fallback values from Framework/Resources/config/translation.yaml
            $this->translator->setFallbackLocales(['en_GB', 'en']);
            $this->translator->setLocale('en-GB');
        }
    }

    /**
     * Injects temporary settings for translation which differ from Context.
     * Call resetInjection() when specific translation is done
     */
    public function injectSettings(string $salesChannelId, string $languageId, string $locale, Context $context): void
    {
        $this->localeBeforeInject = $this->getLocale();
        $this->salesChannelId = $salesChannelId;
        $this->setLocale($locale);
        $this->resolveSnippetSetId($salesChannelId, $languageId, $locale);
        $this->getCatalogue($locale);
    }

    public function resetInjection(): void
    {
        if ($this->localeBeforeInject === null) {
            // Nothing was injected, so no need to reset
            return;
        }

        $this->setLocale($this->localeBeforeInject);
        $this->snippetSetId = null;
        $this->salesChannelId = null;
    }

    public function getSnippetSetId(?string $locale = null): ?string
    {
        $snippetSetId = $this->snippetSetId;
        $currentRequest = $this->requestStack->getMainRequest();

        // when document is rendered from admin, SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID is not set thus we use snippetSetId from injectSetting method
        if ($currentRequest !== null && $currentRequest->attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID)) {
            $snippetSetId = $currentRequest->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID);
        }

        if ($locale === null) {
            return $this->snippetSetId = $snippetSetId;
        }
        // If locale parameter is using, prioritize it over snippet set of request
        if (\array_key_exists($locale, $this->snippets)) {
            return $this->snippets[$locale];
        }

        // get snippet set by locale but in case there are more than one sets with a same locale, we should prioritize the domain's snippet set
        $snippetSetIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM snippet_set WHERE iso = :iso', ['iso' => $locale]);

        if (!empty($snippetSetIds)) {
            $snippetSetId = \in_array($snippetSetId, $snippetSetIds, true) ? $snippetSetId : $snippetSetIds[0];
        }

        $this->snippets[$locale] = $snippetSetId;

        return $this->snippetSetId = $snippetSetId;
    }

    /**
     * @return array<int, MessageCatalogueInterface>
     */
    public function getCatalogues(): array
    {
        return array_values($this->isCustomized);
    }

    private function isFallbackLocaleCatalogue(MessageCatalogueInterface $catalog, string $fallbackLocale): bool
    {
        return mb_strpos($catalog->getLocale(), $fallbackLocale) === 0;
    }

    /**
     * Shopware uses dashes in all locales
     * if the catalogue does not contain any dashes it means it is a symfony fallback catalogue
     * in that case we should not add the shopware fallback catalogue as it would result in circular references
     */
    private function isShopwareLocaleCatalogue(MessageCatalogueInterface $catalog): bool
    {
        return mb_strpos($catalog->getLocale(), '-') !== false;
    }

    private function resolveSnippetSetId(string $salesChannelId, string $languageId, string $locale): void
    {
        $snippetSetId = $this->snippetService->findSnippetSetId($salesChannelId, $languageId, $locale);

        $this->snippetSetId = $snippetSetId;
    }

    /**
     * Add language specific snippets provided by the admin
     */
    private function getCustomizedCatalog(MessageCatalogueInterface $catalog, ?string $fallbackLocale): MessageCatalogueInterface
    {
        try {
            $snippetSetId = $this->getSnippetSetId($catalog->getLocale());
        } catch (DriverException) {
            // this allows us to use the translator even if there's no db connection yet
            return $catalog;
        }

        if (!$snippetSetId) {
            return $catalog;
        }

        if (\array_key_exists($snippetSetId, $this->isCustomized)) {
            return $this->isCustomized[$snippetSetId];
        }

        $snippets = $this->loadSnippets($catalog, $snippetSetId, $fallbackLocale);

        $newCatalog = clone $catalog;
        $newCatalog->add($snippets);

        return $this->isCustomized[$snippetSetId] = $newCatalog;
    }

    /**
     * @return array<string, string>
     */
    private function loadSnippets(MessageCatalogueInterface $catalog, string $snippetSetId, ?string $fallbackLocale): array
    {
        $this->resolveSalesChannelId();

        $key = sprintf('translation.catalog.%s.%s', $this->salesChannelId ?: 'DEFAULT', $snippetSetId);

        return $this->cache->get($key, function (ItemInterface $item) use ($catalog, $snippetSetId, $fallbackLocale) {
            $item->tag('translation.catalog.' . $snippetSetId);
            $item->tag(sprintf('translation.catalog.%s', $this->salesChannelId ?: 'DEFAULT'));

            return $this->snippetService->getStorefrontSnippets($catalog, $snippetSetId, $fallbackLocale, $this->salesChannelId);
        });
    }

    private function getFallbackLocale(): string
    {
        try {
            return $this->languageLocaleProvider->getLocaleForLanguageId(Defaults::LANGUAGE_SYSTEM);
        } catch (ConnectionException) {
            // this allows us to use the translator even if there's no db connection yet
            return 'en-GB';
        }
    }

    private function resolveSalesChannelId(): void
    {
        if ($this->salesChannelId !== null) {
            return;
        }

        $request = $this->requestStack->getMainRequest();

        if (!$request) {
            return;
        }

        $this->salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
    }
}
