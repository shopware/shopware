<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class Translator extends AbstractTranslator
{
    use TranslatorTrait;

    /**
     * @var TranslatorInterface|TranslatorBagInterface|WarmableInterface
     */
    private $translator;

    private RequestStack $requestStack;

    private CacheItemPoolInterface $cache;

    private array $isCustomized = [];

    private MessageFormatterInterface $formatter;

    private SnippetService $snippetService;

    private ?string $fallbackLocale = null;

    private EntityRepositoryInterface $languageRepository;

    private ?string $snippetSetId = null;

    private ?string $localeBeforeInject = null;

    private string $environment;

    private array $keys = ['all' => true];

    private array $traces = [];

    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack,
        CacheItemPoolInterface $cache,
        MessageFormatterInterface $formatter,
        SnippetService $snippetService,
        EntityRepositoryInterface $languageRepository,
        string $environment
    ) {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->cache = $cache;
        $this->formatter = $formatter;
        $this->snippetService = $snippetService;
        $this->languageRepository = $languageRepository;
        $this->environment = $environment;
    }

    public static function buildName(string $id): string
    {
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
        \assert($this->translator instanceof TranslatorBagInterface);
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

        return $this->getCustomizedCatalog($catalog, $fallbackLocale);
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
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
    public function setLocale($locale): void
    {
        \assert($this->translator instanceof LocaleAwareInterface);
        $this->translator->setLocale($locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale(): string
    {
        \assert($this->translator instanceof LocaleAwareInterface);

        return $this->translator->getLocale();
    }

    /**
     * @param string $cacheDir
     */
    public function warmUp($cacheDir): void
    {
        if ($this->translator instanceof WarmableInterface) {
            $this->translator->warmUp($cacheDir);
        }
    }

    public function resetInMemoryCache(): void
    {
        $this->isCustomized = [];
        $this->fallbackLocale = null;
        $this->snippetSetId = null;
    }

    /**
     * Injects temporary settings for translation which differ from Context.
     * Call resetInjection() when specific translation is done
     */
    public function injectSettings(string $salesChannelId, string $languageId, string $locale, Context $context): void
    {
        $this->localeBeforeInject = $this->getLocale();
        $this->setLocale($locale);
        $this->resolveSnippetSetId($salesChannelId, $languageId, $locale, $context);
        $this->getCatalogue($locale);
    }

    public function resetInjection(): void
    {
        \assert($this->localeBeforeInject !== null);
        $this->setLocale($this->localeBeforeInject);
        $this->snippetSetId = null;
    }

    public function getSnippetSetId(): ?string
    {
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
    private function getCustomizedCatalog(MessageCatalogueInterface $catalog, ?string $fallbackLocale): MessageCatalogueInterface
    {
        $snippetSetId = $this->getSnippetSetId();
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

    private function loadSnippets(MessageCatalogueInterface $catalog, string $snippetSetId, ?string $fallbackLocale): array
    {
        $cacheItem = $this->cache->getItem('translation.catalog.' . $snippetSetId);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $snippets = $this->snippetService->getStorefrontSnippets($catalog, $snippetSetId, $fallbackLocale);

        $cacheItem->set($snippets);
        $this->cache->save($cacheItem);

        return $snippets;
    }

    private function getFallbackLocale(): string
    {
        if ($this->fallbackLocale) {
            return $this->fallbackLocale;
        }

        $criteria = new Criteria();
        $criteria->setTitle('snippet-translator::load-fallback');

        $criteria->addFilter(new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM));
        $criteria->addAssociation('locale');

        $defaultLanguage = $this->languageRepository->search($criteria, Context::createDefaultContext())->get(Defaults::LANGUAGE_SYSTEM);

        return $this->fallbackLocale = $defaultLanguage->getLocale()->getCode();
    }
}
