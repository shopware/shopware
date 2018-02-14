<?php

namespace Shopware\DbalIndexing\SeoUrl;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Category\Struct\CategorySearchResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Seo\Definition\SeoUrlDefinition;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\DbalIndexing\Common\QueryIterator;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Indexer\IndexerInterface;
use Shopware\Defaults;
use Shopware\Framework\Doctrine\MultiInsertQueryQueue;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;

class ListingPageSeoUrlIndexer implements IndexerInterface
{
    public const ROUTE_NAME = 'listing_page';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SlugifyInterface
     */
    private $slugify;

    /**
     * @var RouterInterface
     */
    private $router;


    /**
     * @var ShopRepository
     */
    private $shopRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    public function __construct(
        Connection $connection,
        SlugifyInterface $slugify,
        RouterInterface $router,
        CategoryRepository $categoryRepository,
        ShopRepository $shopRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->connection = $connection;
        $this->slugify = $slugify;
        $this->router = $router;
        $this->shopRepository = $shopRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->categoryRepository = $categoryRepository;
    }

    public function index(\DateTime $timestamp): void
    {
        $shops = $this->shopRepository->search(new Criteria(), TranslationContext::createDefaultContext());

        $liveVersionId = Uuid::fromString(Defaults::LIVE_VERSION);

        foreach ($shops as $shop) {
            $context = TranslationContext::createFromShop($shop);

            $iterator = new RepositoryIterator($this->categoryRepository, $context);

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent(
                    sprintf('Start indexing listing page seo urls for shop %s', $shop->getName()),
                    $iterator->getTotal()
                )
            );

            $insertQuery = new MultiInsertQueryQueue($this->connection, 250, false, true);

            /** @var CategorySearchResult $categories */
            while ($categories = $iterator->fetch()) {
                $canonicals = $this->fetchCanonicals($categories->getIds(), $shop->getId());

                foreach ($categories as $category) {
                    
                    $existing = [
                        'id' => Uuid::uuid4()->getBytes(),
                        'isModified' => 0
                    ];
                    if (array_key_exists($category->getId(), $canonicals)) {
                        $existing = $canonicals[$category->getId()];
                    }

                    if ($existing['isModified']) {
                        continue;
                    }

                    $pathInfo = $this->router->generate(self::ROUTE_NAME, ['id' => $category->getId()]);

                    $names = $category->getPathNamesArray();
                    $names[] = $category->getName();

                    $seoUrl = array_map(function(string $name) {
                        return $this->slugify->slugify($name);    
                    }, $names);

                    if (empty($seoUrl)) {
                        continue;
                    }

                    $seoUrl = implode('/', $seoUrl);

                    $data = [
                        'id' => $existing['id'],
                        'version_id' => $liveVersionId->getBytes(),
                        'shop_id' => Uuid::fromString($shop->getId())->getBytes(),
                        'shop_version_id' => $liveVersionId->getBytes(),
                        'name' => self::ROUTE_NAME,
                        'foreign_key' => Uuid::fromString($category->getId())->getBytes(),
                        'foreign_key_version_id' => $liveVersionId->getBytes(),
                        'path_info' => $pathInfo,
                        'seo_path_info' => $seoUrl,
                        'is_canonical' => 1,
                        'is_modified' => 0,
                        'created_at' => $timestamp->format('Y-m-d H:i:s'),
                        'updated_at' => $timestamp->format('Y-m-d H:i:s'),
                    ];

                    $insertQuery->addInsert(SeoUrlDefinition::getEntityName(), $data);

                }

                $this->eventDispatcher->dispatch(
                    ProgressAdvancedEvent::NAME,
                    new ProgressAdvancedEvent($categories->count())
                );

                $insertQuery->execute();
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent(sprintf('Finished indexing listing page seo urls for shop %s', $shop->getName()))
            );
        }
    }

    public function refresh(GenericWrittenEvent $event): void
    {

    }

    private function fetchCanonicals(array $categoryIds, string $shopId)
    {
        $categoryIds = array_map(function($id) {
            return Uuid::fromString($id)->getBytes();
        }, $categoryIds);

        $query = $this->connection->createQueryBuilder();

        $query->select([
            'HEX(seo_url.foreign_key) as categoryId',
            'seo_url.id as id',
            'seo_url.is_modified as isModified'
        ]);
        $query->from('seo_url', 'seo_url');

        $query->andWhere('seo_url.name = :name');
        $query->andWhere('seo_url.shop_id = :shop');
        $query->andWhere('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.foreign_key IN (:ids)');

        $query->setParameter('ids', $categoryIds, Connection::PARAM_STR_ARRAY);
        $query->setParameter(':name', self::ROUTE_NAME);
        $query->setParameter(':shop', Uuid::fromString($shopId)->getBytes());

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);
    }
}