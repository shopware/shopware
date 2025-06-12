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
        $salesChannelIds = $domains->getKeys();
        $productIds = $salesChannelIds ? $this->fetchProductIds($salesChannelIds) : null;

        $extra = [];
        $requestStatus = [];
        foreach ($domains as $salesChannelId => $domain) {
            $productId = $productIds[$salesChannelId] ?? null;

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

    /**
     * @param list<string> $salesChannelIds
     *
     * @return array<string, string>
     */
    private function fetchProductIds(array $salesChannelIds): array
    {
        $sql = <<<'SQL'
            SELECT LOWER(HEX(`product_visibility`.`sales_channel_id`)),
                   LOWER(HEX(`product`.`id`))
            FROM `product`
            INNER JOIN `product_visibility` ON `product`.`id` = `product_visibility`.`product_id`
                AND `product`.`version_id` = `product_visibility`.`product_version_id`
            WHERE `product`.`active` = 1
                AND `product`.`stock` > 0
                AND `product_visibility`.`sales_channel_id` IN (:salesChannelIds)
        SQL;

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['salesChannelIds' => Uuid::fromHexToBytesList($salesChannelIds)],
            ['salesChannelIds' => ArrayParameterType::BINARY]
        );

        return FetchModeHelper::keyPair($result);
    }
}
