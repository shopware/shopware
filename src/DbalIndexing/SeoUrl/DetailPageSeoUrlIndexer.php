<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\SeoUrl;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\DBAL\Connection;
use Shopware\Framework\Struct\Uuid;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductSearchResult;
use Shopware\Api\Seo\Definition\SeoUrlDefinition;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Indexer\IndexerInterface;
use Shopware\Defaults;
use Shopware\Framework\Doctrine\MultiInsertQueryQueue;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;

class DetailPageSeoUrlIndexer implements IndexerInterface
{
    public const ROUTE_NAME = 'detail_page';
    public const LIMIT = 50;

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
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var ShopRepository
     */
    private $shopRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        Connection $connection,
        SlugifyInterface $slugify,
        RouterInterface $router,
        ProductRepository $productRepository,
        ShopRepository $shopRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->connection = $connection;
        $this->slugify = $slugify;
        $this->router = $router;
        $this->productRepository = $productRepository;
        $this->shopRepository = $shopRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function index(\DateTime $timestamp): void
    {
        $shops = $this->shopRepository->search(new Criteria(), ShopContext::createDefaultContext());

        $liveVersionId = Uuid::fromStringToBytes(Defaults::LIVE_VERSION);

        foreach ($shops as $shop) {
            $context = ShopContext::createFromShop($shop);

            $iterator = new RepositoryIterator($this->productRepository, $context);

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent(
                    sprintf('Start indexing detail page seo urls for shop %s', $shop->getName()),
                    $iterator->getTotal()
                )
            );

            $insertQuery = new MultiInsertQueryQueue($this->connection, 250, false, true);

            /** @var ProductSearchResult $products */
            while ($products = $iterator->fetch()) {
                $canonicals = $this->fetchCanonicals($products->getIds(), $shop->getId());

                foreach ($products as $product) {
                    $existing = [
                        'id' => Uuid::uuid4()->getBytes(),
                        'isModified' => 0,
                    ];
                    if (array_key_exists($product->getId(), $canonicals)) {
                        $existing = $canonicals[$product->getId()];
                    }

                    if ($existing['isModified']) {
                        continue;
                    }

                    $pathInfo = $this->router->generate(self::ROUTE_NAME, ['id' => $product->getId()]);

                    $seoUrl = $this->slugify->slugify($product->getName());

                    $data = [
                        'id' => $existing['id'],
                        'version_id' => $liveVersionId,
                        'shop_id' => Uuid::fromStringToBytes($shop->getId()),
                        'shop_version_id' => $liveVersionId,
                        'name' => self::ROUTE_NAME,
                        'foreign_key' => Uuid::fromStringToBytes($product->getId()),
                        'foreign_key_version_id' => $liveVersionId,
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
                    new ProgressAdvancedEvent($products->count())
                );

                $insertQuery->execute();
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent(sprintf('Finished indexing detail page seo urls for shop %s', $shop->getName()))
            );
        }
    }

    public function refresh(GenericWrittenEvent $event): void
    {
    }

    private function fetchCanonicals(array $productIds, string $shopId)
    {
        $productIds = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $productIds);

        $query = $this->connection->createQueryBuilder();

        $query->select([
            'HEX(seo_url.foreign_key) as productId',
            'seo_url.id as id',
            'seo_url.is_modified as isModified',
        ]);
        $query->from('seo_url', 'seo_url');

        $query->andWhere('seo_url.name = :name');
        $query->andWhere('seo_url.shop_id = :shop');
        $query->andWhere('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.foreign_key IN (:ids)');

        $query->setParameter('ids', $productIds, Connection::PARAM_STR_ARRAY);
        $query->setParameter(':name', self::ROUTE_NAME);
        $query->setParameter(':shop', Uuid::fromStringToBytes($shopId));

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    }
}
