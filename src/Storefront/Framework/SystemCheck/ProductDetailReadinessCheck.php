<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
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
class ProductDetailReadinessCheck extends BaseCheck
{
    private const DETAIL_PAGE = 'frontend.detail.page';

    private const MESSAGE_SUCCESS = 'Product detail pages are OK for provided sales channels.';

    private const MESSAGE_FAILURE = 'Some or all product detail pages are unhealthy.';

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
        return 'ProductDetailReadiness';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::readiness();
    }

    private function doRun(): Result
    {
        $domains = $this->domainProvider->fetchSalesChannelDomains();

        $extra = [];
        $requestStatus = [];
        foreach ($domains as $salesChannelId => $domain) {
            $productId = $this->fetchActiveProductIdBySalesChannelId($salesChannelId);

            if ($productId === null) {
                continue;
            }

            $url = $this->util->generateDomainUrl($domain->url, self::DETAIL_PAGE, [
                'productId' => $productId,
            ]);

            $request = Request::create($url);
            $result = $this->util->handleRequest($request);

            $status = $result->responseCode >= Response::HTTP_BAD_REQUEST ? Status::FAILURE : Status::OK;
            $requestStatus[$status->name] = $status;

            $extra[] = $result->getVars();
        }

        if ($requestStatus === []) {
            return $this->util->createEmptyResult($this->name(), 'No sales channels with product detail pages found.');
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

    private function fetchActiveProductIdBySalesChannelId(string $salesChannelId): ?string
    {
        $sql = <<<'SQL'
            SELECT LOWER(HEX(p.id)) as product_id
            FROM product p
            WHERE
                p.version_id = :versionId
                AND p.active = 1
                AND p.stock > 0
                AND EXISTS (
                    SELECT 1 FROM product_visibility pv
                    WHERE pv.product_id = p.id
                        AND pv.product_version_id = p.version_id
                        AND pv.sales_channel_id = :salesChannelId
                )
            ORDER BY p.id
            LIMIT 1;
        SQL;

        return $this->connection->fetchOne(
            $sql,
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId), 'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        ) ?: null;
    }
}
