<?php declare(strict_types=1);

namespace Shopware\Storefront\DbalIndexing\SeoUrl;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\DBAL\Connection;
use Shopware\Application\Application\ApplicationRepository;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Content\Product\ProductRepository;
use Shopware\Content\Product\Struct\ProductSearchResult;
use Shopware\Defaults;
use Shopware\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Framework\Event\ProgressAdvancedEvent;
use Shopware\Framework\Event\ProgressFinishedEvent;
use Shopware\Framework\Event\ProgressStartedEvent;
use Shopware\Framework\ORM\Dbal\Common\EventIdExtractor;
use Shopware\Framework\ORM\Dbal\Common\RepositoryIterator;
use Shopware\Framework\ORM\Dbal\Indexing\IndexerInterface;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\Struct\Uuid;
use Shopware\Storefront\Api\Seo\Definition\SeoUrlDefinition;
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
     * @var \Shopware\Content\Product\ProductRepository
     */
    private $productRepository;

    /**
     * @var ApplicationRepository
     */
    private $applicationRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var \Shopware\Framework\ORM\Dbal\Common\EventIdExtractor
     */
    private $eventIdExtractor;

    public function __construct(
        Connection $connection,
        SlugifyInterface $slugify,
        RouterInterface $router,
        ProductRepository $productRepository,
        ApplicationRepository $applicationRepository,
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor
    ) {
        $this->connection = $connection;
        $this->slugify = $slugify;
        $this->router = $router;
        $this->productRepository = $productRepository;
        $this->applicationRepository = $applicationRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        $applications = $this->applicationRepository->search(new Criteria(), ApplicationContext::createDefaultContext($tenantId));

        foreach ($applications as $application) {
            $context = ApplicationContext::createFromApplication($application);

            $iterator = new RepositoryIterator($this->productRepository, $context);

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent(
                    sprintf('Start indexing detail page seo urls for application %s', $application->getName()),
                    $iterator->getTotal()
                )
            );

            /* @var ProductSearchResult $products */
            while ($ids = $iterator->fetchIds()) {
                $this->updateProducts($ids, $context);

                $this->eventDispatcher->dispatch(
                    ProgressAdvancedEvent::NAME,
                    new ProgressAdvancedEvent(count($ids))
                );
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent(sprintf('Finished indexing detail page seo urls for application %s', $application->getName()))
            );
        }
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        $ids = $this->eventIdExtractor->getProductIds($event);

        if (empty($ids)) {
            return;
        }

        $this->updateProducts($ids, $event->getContext());
    }

    private function fetchCanonicals(array $productIds, string $applicationId, string $tenantId): array
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
        $query->andWhere('seo_url.tenant_id = :tenant');
        $query->andWhere('seo_url.application_id = :application');
        $query->andWhere('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.foreign_key IN (:ids)');

        $query->setParameter('ids', $productIds, Connection::PARAM_STR_ARRAY);
        $query->setParameter('name', self::ROUTE_NAME);
        $query->setParameter('application', Uuid::fromStringToBytes($applicationId));
        $query->setParameter('tenant', Uuid::fromStringToBytes($tenantId));

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    }

    private function updateProducts(array $ids, ApplicationContext $context): void
    {
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $liveVersionId = Uuid::fromStringToBytes(Defaults::LIVE_VERSION);

        $products = $this->productRepository->readBasic($ids, $context);

        $canonicals = $this->fetchCanonicals($products->getIds(), $context->getApplicationId(), $context->getTenantId());
        $timestamp = new \DateTime();

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
                'tenant_id' => Uuid::fromStringToBytes($context->getTenantId()),
                'version_id' => $liveVersionId,
                'application_id' => Uuid::fromStringToBytes($context->getApplicationId()),
                'application_tenant_id' => Uuid::fromStringToBytes($context->getTenantId()),
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

        $insertQuery->execute();
    }
}
