<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Translation\Formatter\ChoiceMessageFormatterInterface;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class Translator implements TranslatorInterface, TranslatorBagInterface, LegacyTranslatorInterface
{
    use TranslatorTrait;

    /**
     * @var TranslatorInterface|TranslatorBagInterface|LegacyTranslatorInterface
     */
    private $translator;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var MessageFormatterInterface
     */
    private $formatter;

    /**
     * @var SnippetService
     */
    private $snippetService;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var string|null
     */
    private $localeBeforeInject = null;

    /**
     * @var string
     */
    private $environment;

    /**
     * @var TranslatorRequestCache
     */
    private $translatorRequestCache;

    public function __construct(
        TranslatorInterface $translator,
        CacheItemPoolInterface $cache,
        MessageFormatterInterface $formatter,
        SnippetService $snippetService,
        EntityRepositoryInterface $languageRepository,
        string $environment,
        TranslatorRequestCache $translatorRequestCache
    ) {
        $this->translator = $translator;
        $this->cache = $cache;
        $this->formatter = $formatter;
        $this->snippetService = $snippetService;
        $this->languageRepository = $languageRepository;
        $this->environment = $environment;
        $this->translatorRequestCache = $translatorRequestCache;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale = null): MessageCatalogueInterface
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

        return $this->getCustomizedCatalog($catalog, $fallbackLocale);
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        if ($domain === null) {
            $domain = 'messages';
        }

        return $this->formatter->format($this->getCatalogue($locale)->get($id, $domain), $locale, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
    {
        if (!$this->formatter instanceof ChoiceMessageFormatterInterface) {
            throw new LogicException(sprintf('The formatter "%s" does not support plural translations.', \get_class($this->formatter)));
        }

        if ($domain === null) {
            $domain = 'messages';
        }

        $catalogue = $this->getCatalogue($locale);
        $locale = $catalogue->getLocale();
        while (!$catalogue->defines($id, $domain)) {
            if ($cat = $catalogue->getFallbackCatalogue()) {
                $catalogue = $cat;
                $locale = $catalogue->getLocale();
            } else {
                break;
            }
        }

        return $this->formatter->choiceFormat($catalogue->get($id, $domain), $number, $locale, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale): void
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

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir): void
    {
        if ($this->translator instanceof WarmableInterface) {
            $this->translator->warmUp($cacheDir);
        }
    }

    public function resetInMemoryCache(): void
    {
        $this->translatorRequestCache->reset();
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
        $this->setLocale($this->localeBeforeInject);
        $this->translatorRequestCache->setSnippetSetId(null);
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
            $this->translatorRequestCache->setSnippetSetId(null);
        } else {
            $this->translatorRequestCache->setSnippetSetId($snippetSet->getId());
        }
    }

    /**
     * Add language specific snippets provided by the admin
     */
    private function getCustomizedCatalog(MessageCatalogueInterface $catalog, ?string $fallbackLocale): MessageCatalogueInterface
    {
        $snippetSetId = $this->translatorRequestCache->getSnippetSetId();
        if (!$snippetSetId) {
            return $catalog;
        }

        if (array_key_exists($snippetSetId, $this->translatorRequestCache->getIsCustomized())) {
            return $this->translatorRequestCache->getIsCustomized()[$snippetSetId];
        }

        $snippets = $this->loadSnippets($catalog, $snippetSetId, $fallbackLocale);

        $newCatalog = clone $catalog;
        $newCatalog->add($snippets);

        $customized = $this->translatorRequestCache->getIsCustomized();
        $customized[$snippetSetId] = $newCatalog;
        $this->translatorRequestCache->setIsCustomized($customized);

        return $newCatalog;
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
        if ($this->translatorRequestCache->getFallbackLocale()) {
            return $this->translatorRequestCache->getFallbackLocale();
        }

        $criteria = new Criteria();
        $criteria->setTitle('snippet-translator::load-fallback');

        $criteria->addFilter(new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM));
        $criteria->addAssociation('locale');

        $defaultLanguage = $this->languageRepository->search($criteria, Context::createDefaultContext())->get(Defaults::LANGUAGE_SYSTEM);

        $this->translatorRequestCache->setFallbackLocale($defaultLanguage->getLocale()->getCode());

        return $this->translatorRequestCache->getFallbackLocale();
    }
}
