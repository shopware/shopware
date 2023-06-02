<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
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
        private readonly SnippetService $snippetService
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

    /**
     * @return mixed|null All kind of data could be cached
     */
    public function trace(string $key, \Closure $param)
    {
        $this->traces[$key] = [];
        $this->keys[$key] = true;

        $result = $param();

        unset($this->keys[$key]);

        return $result;
    }

    /**
     * @return array<int, string>
     */
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
            //fallback locale and current locale has the same localization -> reset fallback
            // or locale is symfony style locale so we shouldn't add shopware fallbacks as it may lead to circular references
            $fallbackLocale = null;
        }

        // disable fallback logic to display symfony warnings
        if ($this->environment !== 'prod') {
            $fallbackLocale = null;
        }

        return $this->getCustomizedCatalog($catalog, $fallbackLocale, $locale);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if ($domain === null) {
            $domain = 'messages';
        }

        foreach (array_keys($this->keys) as $trace) {
            $this->traces[$trace][self::buildName($id)] = true;
        }

        return $this->formatter->format($this->getCatalogue($locale)->get($id, $domain), $locale ?? $this->getFallbackLocale(), $parameters);
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

    /**
     * @deprecated tag:v6.6.0 - Will be removed, use `reset` instead
     */
    public function resetInMemoryCache(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use reset() instead')
        );
        $this->reset();
    }

    public function reset(): void
    {
        $this->isCustomized = [];
        $this->snippetSetId = null;
        if ($this->translator instanceof SymfonyTranslator) {
            // Reset FallbackLocale in memory cache of symfony implementation
            // set fallback values from Framework/Resources/config/translation.yaml
            $this->translator->setFallbackLocales(['en_GB', 'en']);
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
        $this->resolveSnippetSetId($salesChannelId, $languageId, $locale, $context);
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
        if ($locale !== null) {
            if (\array_key_exists($locale, $this->snippets)) {
                return $this->snippets[$locale];
            }

            $snippetSetId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM snippet_set WHERE iso = :iso', ['iso' => $locale]);
            if ($snippetSetId !== false) {
                return $this->snippets[$locale] = $snippetSetId;
            }
        }

        if ($this->snippetSetId !== null) {
            return $this->snippetSetId;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $this->snippetSetId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID);

        return $this->snippetSetId;
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

    private function resolveSnippetSetId(string $salesChannelId, string $languageId, string $locale, Context $context): void
    {
        $snippetSet = $this->snippetService->getSnippetSet($salesChannelId, $languageId, $locale, $context);
        if ($snippetSet === null) {
            $this->snippetSetId = null;
        } else {
            $this->snippetSetId = $snippetSet->getId();
        }
    }

    /**
     * Add language specific snippets provided by the admin
     */
    private function getCustomizedCatalog(MessageCatalogueInterface $catalog, ?string $fallbackLocale, ?string $locale = null): MessageCatalogueInterface
    {
        $snippetSetId = $this->getSnippetSetId($locale);

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

        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return;
        }

        $this->salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
    }
}
