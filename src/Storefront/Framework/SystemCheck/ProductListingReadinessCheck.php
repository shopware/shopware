<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 * covered with integration tests/integration/Storefront/Framework/HealthCheck/ProductsReadinessCheckTest.php todo: write integaration test
 * todo: To avoid flakiness, it should return the checked pages and at the same time allow to pass a list of pages that should be changed for any future checks
 */
#[Package('discovery')]
class ProductListingReadinessCheck extends BaseCheck
{
    private const LISTING_PAGE = 'frontend.navigation.page';

    private const MESSAGE_SUCCESS = 'Product listing pages are OK for provided sales channels';

    private const MESSAGE_FAILURE = 'Some or all product listing pages are unhealthy.';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly SalesChannelDomainUtil $util,
        private readonly Connection $connection,
    ) {
    }

    public function run(): Result
    {
        return $this->util->asASalesChannelRequest(
            fn () => $this->util->whileTrustingAllHosts(
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
        $domains = $this->fetchSalesChannelDomains();
        $salesChannelIds = array_keys($domains);
        $navigationIds = $salesChannelIds ? $this->fetchNavigationIds($salesChannelIds) : null;

        $extra = [];
        $requestStatus = [];
        foreach ($domains as $salesChannelId => $domain) {
            $navigationId = $navigationIds[$salesChannelId] ?? null;

            if ($navigationId === null) {
                continue;
            }

            $url = $this->util->generateDomainUrl($domain, self::LISTING_PAGE, [
                'navigationId' => $navigationId,
            ]);

            $request = Request::create($url);
            $requestStart = microtime(true);
            $response = $this->kernel->handle($request);
            $responseTime = microtime(true) - $requestStart;
            $status = $response->getStatusCode() >= Response::HTTP_BAD_REQUEST ? Status::FAILURE : Status::OK;
            $requestStatus[$status->name] = $status;

            $extra[] = [
                'storeFrontUrl' => $url,
                'responseCode' => $response->getStatusCode(),
                'responseTime' => $responseTime,
            ];
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
     * @param list<string> $salesChannelIds
     *
     * @return array<string, string>
     */
    private function fetchNavigationIds(array $salesChannelIds): array
    {
        // it is required to join on a child category because otherwise no cms page is assigned

        $sql = <<<SQL
            SELECT `sales_channel`.`id` AS `sales_channel_id`,
                   LOWER(HEX(`category_child`.`id`)) AS `category_id`
            FROM `category` `category_root`
            INNER JOIN `category` `category_child`
                ON `category_root`.`id` = `category_child`.`parent_id`
                AND `category_root`.`version_id` = `category_child`.`version_id`
            INNER JOIN `cms_page`
                ON `category_child`.`cms_page_id` = `cms_page`.`id`
                AND `category_child`.`version_id` = `cms_page`.`version_id`
            INNER JOIN `sales_channel`
                ON `sales_channel`.`navigation_category_id` = `category_root`.`id`
                and `sales_channel`.`navigation_category_version_id` = `category_root`.`version_id`
            WHERE `cms_page`.`type` = 'product_list'
                AND `category_root`.`active` = 1
                AND `category_child`.`active` = 1
                AND `sales_channel`.`id` IN (:salesChannelIds)
            GROUP BY `sales_channel`.`id`
        SQL;

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['salesChannelIds' => $salesChannelIds],
            ['salesChannelIds' => ArrayParameterType::BINARY]
        );

        return FetchModeHelper::keyPair($result);
    }

    /**
     * @return array<string, string>
     */
    private function fetchSalesChannelDomains(): array
    {
        $sql = <<<'SQL'
            SELECT `sales_channel`.`id`,
                   `sales_channel_domain`.`url`
            FROM `sales_channel_domain`
            INNER JOIN `sales_channel` ON `sales_channel_domain`.`sales_channel_id` = `sales_channel`.`id`
            WHERE `sales_channel`.`type_id` = :typeId
            AND `sales_channel`.`active` = :active
        SQL;

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT), 'active' => 1]
        );

        return FetchModeHelper::keyPair($result);
    }
}
