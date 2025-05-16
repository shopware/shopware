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
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 * covered with integration tests/integration/Storefront/Framework/HealthCheck/SaleChannelsReadinessCheckTest.php
 */
#[Package('framework')]
class SaleChannelsReadinessCheck extends BaseCheck
{
    private const INDEX_PAGE = 'frontend.home.page';

    /**
     * @internal
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Connection $connection,
        private readonly SalesChannelDomainUtil $util,
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
        return 'SaleChannelsReadiness';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::readiness();
    }

    private function doRun(): Result
    {
        $domains = $this->fetchSalesChannelDomains();
        $extra = [];
        $requestStatus = [];
        foreach ($domains as $domain) {
            $url = $this->util->generateDomainUrl($domain, self::INDEX_PAGE);
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
            $finalStatus === Status::OK ? 'All sales channels are OK' : 'Some or all sales channels are unhealthy.',
            $finalStatus === Status::OK,
            $extra
        );
    }

    /**
     * @return array<string>
     */
    private function fetchSalesChannelDomains(): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT `url` FROM `sales_channel_domain`
                    INNER JOIN `sales_channel` ON `sales_channel_domain`.`sales_channel_id` = `sales_channel`.`id`
                    WHERE `sales_channel`.`type_id` = :typeId
                    AND `sales_channel`.`active` = :active',
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT), 'active' => 1]
        );

        return array_map(fn (array $row): string => $row['url'], $result);
    }
}
