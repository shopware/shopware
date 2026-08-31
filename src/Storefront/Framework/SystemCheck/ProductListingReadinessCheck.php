<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\SystemCheck\Util\AbstractSalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class ProductListingReadinessCheck extends BaseCheck
{
    private const LISTING_PAGE = NavigationPageSeoUrlRoute::ROUTE_NAME;

    private const MESSAGE_SUCCESS = 'Product listing pages are OK for provided sales channels.';

    private const MESSAGE_FAILURE = 'Some or all product listing pages are unhealthy.';

    /**
     * @param SalesChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly SalesChannelDomainUtil $util,
        private readonly Connection $connection,
        private readonly AbstractSalesChannelDomainProvider $domainProvider,
        private readonly SalesChannelRepository $categoryRepository,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    ) {
    }

    public function run(): Result
    {
        return $this->util->runAsSalesChannelRequest(
            fn () => $this->util->runWhileTrustingAllHosts(
                fn () => $this->doRun()
            )
        );
    }

    public function category(): Category
    {
        return Category::FEATURE;
    }

    public function name(): string
    {
        return 'ProductListingReadiness';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::readiness();
    }

    private function doRun(): Result
    {
        $domains = $this->domainProvider->fetchSalesChannelDomains();
        $salesChannelIds = $domains->getKeys();
        $navigationIds = $salesChannelIds ? $this->fetchNavigationIds($salesChannelIds) : [];

        $extra = [];
        $requestStatus = [];
        foreach ($domains as $salesChannelId => $domain) {
            $navigationId = $this->resolveVisibleNavigationId($salesChannelId, $navigationIds[$salesChannelId] ?? []);

            if ($navigationId === null) {
                continue;
            }

            $url = $this->util->generateDomainUrl($domain->url, self::LISTING_PAGE, [
                'navigationId' => $navigationId,
            ]);

            $request = Request::create($url);
            $result = $this->util->handleRequest($request);

            $status = $result->responseCode >= Response::HTTP_BAD_REQUEST ? Status::FAILURE : Status::OK;
            $requestStatus[$status->name] = $status;

            $extra[] = $result->getVars();
        }

        if ($requestStatus === []) {
            return $this->util->createEmptyResult($this->name(), 'No sales channels with product listing pages found.');
        }

        $finalStatus = \count($requestStatus) === 1 ? current($requestStatus) : Status::ERROR;

        return new Result(
            $this->name(),
            $finalStatus,
            $finalStatus === Status::OK ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILURE,
            $finalStatus === Status::OK,
            $extra
        );
    }

    /**
     * @description The category is resolved through the sales channel repository on purpose, so extensions that
     * restrict category visibility by rules (for example via the sales_channel.category.process.criteria event)
     * are taken into account. Otherwise the check could pick a category the storefront refuses to render for an
     * anonymous visitor, which would report an intentional restriction as an unhealthy product listing page.
     *
     * @param list<string> $candidateIds
     */
    private function resolveVisibleNavigationId(string $salesChannelId, array $candidateIds): ?string
    {
        if ($candidateIds === []) {
            return null;
        }

        $context = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);

        $criteria = new Criteria($candidateIds);
        $criteria->setTitle('product-listing-readiness-check');
        $criteria->addFilter(new EqualsFilter('active', true));

        $visibleIds = $this->categoryRepository->searchIds($criteria, $context)->getIds();

        // keep the order of the candidates, so child listing pages are preferred over the navigation category
        foreach ($candidateIds as $candidateId) {
            if (\in_array($candidateId, $visibleIds, true)) {
                return $candidateId;
            }
        }

        return null;
    }

    /**
     * @description This query is necessary to determine the categories that are configured for storefront listing
     * pages per sales channel. It covers cases where the navigation category itself or one of its direct child
     * categories is assigned a CMS page of type 'product_list', and only considers active categories and sales
     * channels. All matching categories are returned, child categories first, so the check can fall back to the
     * next candidate if a category cannot be rendered for an anonymous visitor.
     *
     * @param list<string> $salesChannelIds
     *
     * @return array<string, list<string>>
     */
    private function fetchNavigationIds(array $salesChannelIds): array
    {
        $sql = <<<'SQL'
            SELECT LOWER(HEX(`sales_channel`.`id`)) AS `sales_channel_id`,
                   LOWER(HEX(`category_child`.`id`)) AS `category_id`,
                   0 AS `is_navigation_category`
            FROM `category` `category_root`
            INNER JOIN `sales_channel`
                ON `sales_channel`.`navigation_category_id` = `category_root`.`id`
                AND `sales_channel`.`navigation_category_version_id` = `category_root`.`version_id`
            INNER JOIN `category` `category_child`
                ON `category_root`.`id` = `category_child`.`parent_id`
                AND `category_root`.`version_id` = `category_child`.`version_id`
                AND `category_child`.`active` = 1
            INNER JOIN `cms_page` `cms_page_child`
                ON `category_child`.`cms_page_id` = `cms_page_child`.`id`
                AND `category_child`.`version_id` = `cms_page_child`.`version_id`
                AND `cms_page_child`.`type` = 'product_list'
            WHERE `category_root`.`active` = 1
                AND `sales_channel`.`id` IN (:childSalesChannelIds)
            UNION ALL
            SELECT LOWER(HEX(`sales_channel`.`id`)) AS `sales_channel_id`,
                   LOWER(HEX(`category_root`.`id`)) AS `category_id`,
                   1 AS `is_navigation_category`
            FROM `category` `category_root`
            INNER JOIN `sales_channel`
                ON `sales_channel`.`navigation_category_id` = `category_root`.`id`
                AND `sales_channel`.`navigation_category_version_id` = `category_root`.`version_id`
            INNER JOIN `cms_page` `cms_page_root`
                ON `category_root`.`cms_page_id` = `cms_page_root`.`id`
                AND `category_root`.`version_id` = `cms_page_root`.`version_id`
                AND `cms_page_root`.`type` = 'product_list'
            WHERE `category_root`.`active` = 1
                AND `sales_channel`.`id` IN (:rootSalesChannelIds)
            ORDER BY `sales_channel_id`, `is_navigation_category`, `category_id`
        SQL;

        $result = $this->connection->fetchAllAssociative(
            $sql,
            [
                'childSalesChannelIds' => Uuid::fromHexToBytesList($salesChannelIds),
                'rootSalesChannelIds' => Uuid::fromHexToBytesList($salesChannelIds),
            ],
            [
                'childSalesChannelIds' => ArrayParameterType::BINARY,
                'rootSalesChannelIds' => ArrayParameterType::BINARY,
            ]
        );

        $navigationIds = [];
        foreach ($result as $row) {
            $navigationIds[(string) $row['sales_channel_id']][] = (string) $row['category_id'];
        }

        return $navigationIds;
    }
}
