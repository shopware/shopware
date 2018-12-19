<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Translation;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Snippet\Files\LanguageFileCollection;
use Shopware\Core\Framework\Snippet\Files\LanguageFileInterface;
use Shopware\Core\Framework\Snippet\Services\SnippetFlattenerInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Translation\Formatter\ChoiceMessageFormatterInterface;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Translation\TranslatorInterface;

class Translator implements TranslatorInterface, TranslatorBagInterface
{
    /**
     * @var TranslatorInterface|TranslatorBagInterface
     */
    private $translator;

    /**
     * @var Connection
     */
    private $connection;

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
     * @var LanguageFileCollection
     */
    private $languageFileCollection;

    /**
     * @var MessageFormatterInterface
     */
    private $formatter;

    /**
     * @var SnippetFlattenerInterface
     */
    private $snippetFlattener;

    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack,
        Connection $connection,
        CacheItemPoolInterface $cache,
        LanguageFileCollection $languageFileCollection,
        MessageFormatterInterface $formatter,
        SnippetFlattenerInterface $snippetFlattener
    ) {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->cache = $cache;
        $this->languageFileCollection = $languageFileCollection;
        $this->formatter = $formatter;
        $this->snippetFlattener = $snippetFlattener;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale = null)
    {
        $catalog = $this->translator->getCatalogue($locale);

        return $this->getCustomizedCatalog($catalog);
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = [], $domain = null, $locale = null)
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
    public function setLocale($locale)
    {
        $this->translator->setLocale($locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        return $this->translator->getLocale();
    }

    public function resetInMemoryCache(): void
    {
        $this->isCustomized = [];
    }

    /**
     * Add language specific snippets provided by the admin
     *
     * @param MessageCatalogueInterface $catalog
     *
     * @return MessageCatalogueInterface
     */
    private function getCustomizedCatalog(MessageCatalogueInterface $catalog): MessageCatalogueInterface
    {
        $request = $this->requestStack->getMasterRequest();
        if (!$request || !$request->attributes->has(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)) {
            return $catalog;
        }

        /** @var Context $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        if (array_key_exists($context->getSnippetSetId(), $this->isCustomized)) {
            return $this->isCustomized[$context->getSnippetSetId()];
        }

        $cacheItem = $this->cache->getItem('translation.catalog.' . $context->getSnippetSetId());
        if ($cacheItem->isHit()) {
            $snippets = $cacheItem->get();
        } else {
            $snippets = $this->getSnippets($catalog, $context);

            $cacheItem->set($snippets);
            $this->cache->save($cacheItem);
        }

        $newCatalog = clone $catalog;
        $newCatalog->add($snippets);

        return $this->isCustomized[$context->getSnippetSetId()] = $newCatalog;
    }

    private function fetchSnippetsFromDatabase(Context $context): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select(['snippet.translation_key', 'snippet.value'])
            ->from('snippet')
            ->where('snippet.snippet_set_id = :snippetSetId')
            ->setParameter('snippetSetId', Uuid::fromHexToBytes($context->getSnippetSetId()))
            ->addGroupBy('snippet.translation_key')
            ->addGroupBy('snippet.id');

        $snippets = $query->execute()->fetchAll();

        return FetchModeHelper::keyPair($snippets);
    }

    private function getLocaleBySnippetSetId(string $snippetSetId): string
    {
        $locale = $this->connection->createQueryBuilder()
            ->select(['iso'])
            ->from('snippet_set')
            ->where('id = :snippetSetId')
            ->setParameter('snippetSetId', Uuid::fromHexToBytes($snippetSetId))
            ->execute()
            ->fetchColumn();

        if ($locale === false) {
            $locale = $this->getDefaultLocale();
        }

        return $locale;
    }

    private function getDefaultLocale(): string
    {
        $locale = $this->connection->createQueryBuilder()
            ->select(['code'])
            ->from('locale')
            ->where('id = :localeId')
            ->setParameter('localeId', Uuid::fromHexToBytes(Defaults::LOCALE_SYSTEM))
            ->execute()
            ->fetchColumn();

        return $locale ?: Defaults::LOCALE_EN_GB_ISO;
    }

    private function getSnippets(MessageCatalogueInterface $catalog, Context $context): array
    {
        $locale = $this->getLocaleBySnippetSetId($context->getSnippetSetId());

        $languageFiles = $this->languageFileCollection->getLanguageFilesByIso($locale);
        $fileSnippets = $catalog->all('messages');

        /** @var LanguageFileInterface $languageFile */
        foreach ($languageFiles as $key => $languageFile) {
            $fattenLanguageFileSnippets = $this->snippetFlattener->flatten(
                json_decode(file_get_contents($languageFile->getPath()), true) ?: []
            );

            $fileSnippets = array_replace_recursive(
                $fileSnippets,
                $fattenLanguageFileSnippets
            );
        }

        $snippets = array_replace_recursive(
            $fileSnippets,
            $this->fetchSnippetsFromDatabase($context)
        );

        return $snippets;
    }
}
