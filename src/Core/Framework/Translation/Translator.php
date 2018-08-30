<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Translation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;
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

    public function __construct(TranslatorInterface $translator, RequestStack $requestStack, Connection $connection, CacheItemPoolInterface $cache)
    {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->cache = $cache;
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
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
    {
        return $this->translator->transChoice($id, $number, $parameters, $domain, $locale);
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
     * Add tenant and language specific snippets provided by the admin
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
        $contextHash = md5($context->getTenantId() . $context->getLanguageId() . $context->getFallbackLanguageId());

        if (array_key_exists($contextHash, $this->isCustomized)) {
            return $this->isCustomized[$contextHash];
        }

        $cacheItem = $this->cache->getItem('translation.catalog.' . $contextHash);

        if ($cacheItem->isHit()) {
            $snippets = $cacheItem->get();
        } else {
            $snippets = $this->fetchSnippetsFromDatabase($context);
            $cacheItem->set($snippets);
            $this->cache->save($cacheItem);
        }

        $newCatalog = clone $catalog;
        $newCatalog->add($snippets);

        return $this->isCustomized[$contextHash] = $newCatalog;
    }

    private function getSnippetQuery(string $languageId, string $tenantId): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(['snippet.translation_key', 'snippet.value'])
            ->from('snippet')
            ->where('snippet.tenant_id = :tenantId AND snippet.language_id = :languageId')
            ->setParameter('tenantId', Uuid::fromHexToBytes($tenantId))
            ->setParameter('languageId', Uuid::fromHexToBytes($languageId))
            ->addGroupBy('snippet.translation_key')
        ;
    }

    private function fetchSnippetsFromDatabase(Context $context): array
    {
        $snippets = $this->getSnippetQuery($context->getLanguageId(), $context->getTenantId())->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);

        $fallbackSnippets = [];
        if ($context->hasFallback()) {
            $fallbackSnippets = $this->getSnippetQuery($context->getFallbackLanguageId(), $context->getTenantId())->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);
        }

        return array_merge($fallbackSnippets, $snippets);
    }
}
