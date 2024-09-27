<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchasesSyncService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchasesSyncService::class)]
class InAppPurchasesSyncServiceTest extends TestCase
{
    public function testUpdateActiveInAppPurchases(): void
    {
        $expectedUpsertResponse = [
            ['identifier' => 'TestApp-test', 'appId' => 'test-app-id', 'pluginId' => null, 'expiresAt' => '2099-01-01', 'active' => true],
            ['identifier' => 'TestApp-test2', 'appId' => 'test-app-id', 'pluginId' => null, 'expiresAt' => '2099-01-01', 'active' => true],
        ];

        $client = $this->createMock(ClientInterface::class);
        $client->expects(static::once())
            ->method('request')
            ->willReturn(new Response(200, [], '[{"identifier":"TestApp-test", "active":true, "expiresAt":"2099-01-01"},{"identifier":"TestApp-test2", "active":true, "expiresAt":"2099-01-01"}]'));

        $iapRepository = $this->createMock(EntityRepository::class);
        $iapRepository->expects(static::once())
            ->method('upsert')
            ->with($expectedUpsertResponse);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([['type' => 'app', 'name' => 'TestApp', 'id' => 'test-app-id']]);

        $service = new InAppPurchasesSyncService($client, $iapRepository, $connection, 'https://test.com');
        $service->updateActiveInAppPurchases(Context::createDefaultContext());
    }

    public function testDisableExpiredInAppPurchases(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true),
            new RangeFilter('expiresAt', [RangeFilter::LT => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)])
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('executeQuery')
            ->with('UPDATE in_app_purchase SET active = false WHERE expires_at < NOW()');

        $service = new InAppPurchasesSyncService($this->createMock(ClientInterface::class), $this->createMock(EntityRepository::class), $connection, 'https://test.com');
        $service->disableExpiredInAppPurchases();
    }
}
