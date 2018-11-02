<?php declare(strict_types=1);

namespace Shopware\Storefront\DbalIndexing\SeoUrl;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Util\EventIdExtractor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Api\Seo\SeoUrlDefinition;
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
     * @var RepositoryInterface
     */
    private $applicationRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var RepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var EventIdExtractor
     */
    private $eventIdExtractor;

    public function __construct(
        Connection $connection,
        SlugifyInterface $slugify,
        RouterInterface $router,
        RepositoryInterface $categoryRepository,
        RepositoryInterface $applicationRepository,
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor
    ) {
        $this->connection = $connection;
        $this->slugify = $slugify;
        $this->router = $router;
        $this->applicationRepository = $applicationRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->categoryRepository = $categoryRepository;
        $this->eventIdExtractor = $eventIdExtractor;
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        $applications = $this->applicationRepository->search(new Criteria(), Context::createDefaultContext($tenantId));

        foreach ($applications as $application) {
            $context = Context::createFromSalesChannel($application, SourceContext::ORIGIN_SYSTEM);

            $iterator = new RepositoryIterator($this->categoryRepository, $context);

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent(
                    sprintf('Start indexing listing page seo urls for application %s', $application->getName()),
                    $iterator->getTotal()
                )
            );

            /* @var EntitySearchResult $categories */
            while ($ids = $iterator->fetchIds()) {
                $this->updateCategories($ids, $context);

                $this->eventDispatcher->dispatch(
                    ProgressAdvancedEvent::NAME,
                    new ProgressAdvancedEvent(\count($ids))
                );
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent(sprintf('Finished indexing listing page seo urls for application %s', $application->getName()))
            );
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getCategoryIds($event);

        $this->updateCategories($ids, $event->getContext());
    }

    private function fetchCanonicals(array $categoryIds, string $applicationId, string $tenantId): array
    {
        $categoryIds = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $categoryIds);

        $query = $this->connection->createQueryBuilder();

        $query->select([
            'HEX(seo_url.foreign_key) as categoryId',
            'seo_url.id as id',
            'seo_url.is_modified as isModified',
        ]);
        $query->from('seo_url', 'seo_url');

        $query->andWhere('seo_url.name = :name');
        $query->andWhere('seo_url.sales_channel_id = :application');
        $query->andWhere('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.tenant_id = :tenant');
        $query->andWhere('seo_url.foreign_key IN (:ids)');

        $query->setParameter('ids', $categoryIds, Connection::PARAM_STR_ARRAY);
        $query->setParameter('name', self::ROUTE_NAME);
        $query->setParameter('application', Uuid::fromStringToBytes($applicationId));
        $query->setParameter('tenant', Uuid::fromStringToBytes($tenantId));

        $rows = $query->execute()->fetchAll();

        return FetchModeHelper::groupUnique($rows);
    }

    private function updateCategories(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $categories = $this->categoryRepository->read(new ReadCriteria($ids), $context);

        $canonicals = $this->fetchCanonicals($categories->getIds(), $context->getSourceContext()->getSalesChannelId(), $context->getTenantId());

        $liveVersionId = Uuid::fromStringToBytes(Defaults::LIVE_VERSION);
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $timestamp = new \DateTime();

        foreach ($categories as $category) {
            $existing = [
                'id' => Uuid::uuid4()->getBytes(),
                'isModified' => 0,
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

            $seoUrl = array_map(function (string $name) {
                return $this->slugify->slugify($name);
            }, $names);

            if (empty($seoUrl)) {
                continue;
            }

            $seoUrl = implode('/', $seoUrl);

            $data = [
                'id' => $existing['id'],
                'tenant_id' => Uuid::fromStringToBytes($context->getTenantId()),
                'version_id' => $liveVersionId,
                'sales_channel_id' => Uuid::fromStringToBytes($context->getSourceContext()->getSalesChannelId()),
                'sales_channel_tenant_id' => Uuid::fromStringToBytes($context->getTenantId()),
                'name' => self::ROUTE_NAME,
                'foreign_key' => Uuid::fromStringToBytes($category->getId()),
                'foreign_key_version_id' => $liveVersionId,
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
