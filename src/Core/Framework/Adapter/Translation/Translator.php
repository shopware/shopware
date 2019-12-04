<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\HttpFoundation\RequestStack;
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
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var array
     */
    private $isCustomized = [];

    /**
     * @var MessageFormatterInterface
     */
    private $formatter;

    /**
     * @var SnippetService
     */
    private $snippetService;

    /**
     * @var string
     */
    private $fallbackLocale;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var string|null
     */
    private $snippetSetId = null;

    /**
     * @var string|null
     */
    private $localeBeforeInject = null;

    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack,
        CacheItemPoolInterface $cache,
        MessageFormatterInterface $formatter,
        SnippetService $snippetService,
        EntityRepositoryInterface $languageRepository
    ) {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->cache = $cache;
        $this->formatter = $formatter;
        $this->snippetService = $snippetService;
        $this->languageRepository = $languageRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale = null): MessageCatalogueInterface
    {
        $catalog = $this->translator->getCatalogue($locale);

        $fallbackLocale = $this->getFallbackLocale();
        if (mb_strpos($catalog->getLocale(), $fallbackLocale) !== 0) {
            $catalog->addFallbackCatalogue($this->translator->getCatalogue($fallbackLocale));
        }

        return $this->getCustomizedCatalog($catalog);
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

    public function resetInMemoryCache(): void
    {
        $this->isCustomized = [];
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
        $this->snippetSetId = null;
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
    private function getCustomizedCatalog(MessageCatalogueInterface $catalog): MessageCatalogueInterface
    {
        if ($this->snippetSetId === null) {
            $request = $this->requestStack->getCurrentRequest();
            if (!$request) {
                return $catalog;
            }

            $snippetSetId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID);
            if ($snippetSetId === null) {
                return $catalog;
            }
        } else {
            $snippetSetId = $this->snippetSetId;
        }

        if (array_key_exists($snippetSetId, $this->isCustomized)) {
            return $this->isCustomized[$snippetSetId];
        }

        $cacheItem = $this->cache->getItem('translation.catalog.' . $snippetSetId);
        if ($cacheItem->isHit()) {
            $snippets = $cacheItem->get();
        } else {
            $snippets = $this->snippetService->getStorefrontSnippets($catalog, $snippetSetId);

            $cacheItem->set($snippets);
            $this->cache->save($cacheItem);
        }

        $newCatalog = clone $catalog;
        $newCatalog->add($snippets);

        return $this->isCustomized[$snippetSetId] = $newCatalog;
    }

    private function getFallbackLocale(): string
    {
        if ($this->fallbackLocale) {
            return $this->fallbackLocale;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM));
        $criteria->addAssociation('locale');

        $defaultLanguage = $this->languageRepository->search($criteria, Context::createDefaultContext())->get(Defaults::LANGUAGE_SYSTEM);

        return $this->fallbackLocale = mb_substr($defaultLanguage->getLocale()->getCode(), 0, 2);
    }
}
