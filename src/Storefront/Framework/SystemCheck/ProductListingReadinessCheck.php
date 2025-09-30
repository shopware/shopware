<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\Uuid\Uuid;
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
    private const LISTING_PAGE = 'frontend.navigation.page';

    private const MESSAGE_SUCCESS = 'Product listing pages are OK for provided sales channels.';

    private const MESSAGE_FAILURE = 'Some or all product listing pages are unhealthy.';

    public function __construct(
        private readonly SalesChannelDomainUtil $util,
        private readonly Connection $connection,
        private readonly AbstractSalesChannelDomainProvider $domainProvider,
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
        $navigationIds = $salesChannelIds ? $this->fetchNavigationIds($salesChannelIds) : null;

        $extra = [];
        $requestStatus = [];
        foreach ($domains as $salesChannelId => $domain) {
            $navigationId = $navigationIds[$salesChannelId] ?? null;

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
     * @description This query is necessary to determine the correct navigation category for each sales channel that is configured for storefront listing pages.
     * It covers cases where the navigation category itself or one of its direct child categories is assigned a CMS page of type 'product_list'.
     * This ensures that the check works for both direct and nested category assignments, and only considers active categories and sales channels.
     *
     * @param list<string> $salesChannelIds
     *
     * @return array<string, string>
     */
    private function fetchNavigationIds(array $salesChannelIds): array
    {
        $sql = <<<'SQL'
            SELECT LOWER(HEX(`sales_channel`.`id`)) AS `sales_channel_id`,
                   LOWER(HEX(COALESCE(`category_child`.`id`, `category_root`.`id`))) AS `category_id`
            FROM `category` `category_root`
            LEFT JOIN `category` `category_child`
                ON `category_root`.`id` = `category_child`.`parent_id`
                AND `category_root`.`version_id` = `category_child`.`version_id`
                AND `category_child`.`active` = 1
            LEFT JOIN `cms_page` `cms_page_child`
                ON `category_child`.`cms_page_id` = `cms_page_child`.`id`
                AND `category_child`.`version_id` = `cms_page_child`.`version_id`
                AND `cms_page_child`.`type` = 'product_list'
            LEFT JOIN `cms_page` `cms_page_root`
                ON `category_root`.`cms_page_id` = `cms_page_root`.`id`
                AND `category_root`.`version_id` = `cms_page_root`.`version_id`
                AND `cms_page_root`.`type` = 'product_list'
            INNER JOIN `sales_channel`
                ON `sales_channel`.`navigation_category_id` = `category_root`.`id`
                and `sales_channel`.`navigation_category_version_id` = `category_root`.`version_id`
            WHERE `category_root`.`active` = 1
                AND `sales_channel`.`id` IN (:salesChannelIds)
                AND (
                    `cms_page_child`.`id` IS NOT NULL
                    OR `cms_page_root`.`id` IS NOT NULL
                )
            GROUP BY `sales_channel`.`id`
        SQL;

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['salesChannelIds' => Uuid::fromHexToBytesList($salesChannelIds)],
            ['salesChannelIds' => ArrayParameterType::BINARY]
        );

        return FetchModeHelper::keyPair($result);
    }
}
