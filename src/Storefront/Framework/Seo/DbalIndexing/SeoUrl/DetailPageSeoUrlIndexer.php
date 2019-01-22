<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\DbalIndexing\SeoUrl;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Context\CheckoutContextFactoryInterface;
use Shopware\Core\Content\Product\Util\EventIdExtractor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlDefinition;
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
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EventIdExtractor
     */
    private $eventIdExtractor;

    /**
     * @var CheckoutContextFactoryInterface
     */
    private $checkoutContextFactory;

    public function __construct(
        Connection $connection,
        SlugifyInterface $slugify,
        RouterInterface $router,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor,
        CheckoutContextFactoryInterface $checkoutContextFactory
    ) {
        $this->connection = $connection;
        $this->slugify = $slugify;
        $this->router = $router;
        $this->productRepository = $productRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->checkoutContextFactory = $checkoutContextFactory;
    }

    public function index(\DateTime $timestamp): void
    {
        $defaultContext = Context::createDefaultContext();

        $applications = $this->salesChannelRepository->search(new Criteria(), $defaultContext);

        /** @var SalesChannelEntity $application */
        foreach ($applications as $application) {
            $options = $application->jsonSerialize();
            $options['origin'] = SourceContext::ORIGIN_SYSTEM;
            $checkoutContext = $this->checkoutContextFactory->create(
                Uuid::uuid4()->getHex(),
                $application->getId(),
                $options
            );
            $context = $checkoutContext->getContext();

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
                    new ProgressAdvancedEvent(\count($ids))
                );
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent(sprintf('Finished indexing detail page seo urls for application %s', $application->getName()))
            );
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getProductIds($event);

        if (empty($ids)) {
            return;
        }

        $this->updateProducts($ids, $event->getContext());
    }

    private function fetchCanonicals(array $productIds, string $salesChannelId): array
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
        $query->andWhere('seo_url.sales_channel_id = :salesChannel');
        $query->andWhere('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.foreign_key IN (:ids)');

        $query->setParameter('ids', $productIds, Connection::PARAM_STR_ARRAY);
        $query->setParameter('name', self::ROUTE_NAME);
        $query->setParameter('salesChannel', Uuid::fromStringToBytes($salesChannelId));

        $rows = $query->execute()->fetchAll();

        return FetchModeHelper::groupUnique($rows);
    }

    private function updateProducts(array $ids, Context $context): void
    {
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $products = $this->productRepository->search(new Criteria($ids), $context);

        $canonicals = $this->fetchCanonicals($products->getIds(), $context->getSourceContext()->getSalesChannelId());
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
                'sales_channel_id' => Uuid::fromStringToBytes($context->getSourceContext()->getSalesChannelId()),
                'name' => self::ROUTE_NAME,
                'foreign_key' => Uuid::fromStringToBytes($product->getId()),
                'path_info' => $pathInfo,
                'seo_path_info' => $seoUrl,
                'is_canonical' => 1,
                'is_modified' => 0,
                'created_at' => $timestamp->format(Defaults::DATE_FORMAT),
                'updated_at' => $timestamp->format(Defaults::DATE_FORMAT),
            ];

            $insertQuery->addInsert(SeoUrlDefinition::getEntityName(), $data);
        }

        $insertQuery->execute();
    }
}
