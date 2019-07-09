<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Translation;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Snippet\SnippetService;
use Shopware\Core\SalesChannelRequest;
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

    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack,
        CacheItemPoolInterface $cache,
        MessageFormatterInterface $formatter,
        SnippetService $snippetService
    ) {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->cache = $cache;
        $this->formatter = $formatter;
        $this->snippetService = $snippetService;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale = null): MessageCatalogueInterface
    {
        $catalog = $this->translator->getCatalogue($locale);

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
     * Add language specific snippets provided by the admin
     */
    private function getCustomizedCatalog(MessageCatalogueInterface $catalog): MessageCatalogueInterface
    {
        $request = $this->requestStack->getMasterRequest();
        if (!$request) {
            return $catalog;
        }

        $snippetSetId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID);
        if ($snippetSetId === null) {
            return $catalog;
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
}
