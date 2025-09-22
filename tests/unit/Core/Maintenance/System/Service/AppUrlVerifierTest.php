<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Handler\MockHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopId\UrlVerificationStatus;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * @internal
 */
#[CoversClass(AppUrlVerifier::class)]
class AppUrlVerifierTest extends TestCase
{
    private Connection&MockObject $connection;

    private ShopIdProvider&MockObject $shopIdProvider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
    }

    //    public function testAppUrlReachableReturnsTrueIfAppEnvIsNotProd(): void
    //    {
    //        $verifier = new AppUrlVerifier($this->connection, $this->shopIdProvider);
    //
    //        static::assertTrue($verifier->isAppUrlReachable());
    //    }
    //
    //    public function testAppUrlReachableReturnsTrueIfAppUrlCheckIsDisabled(): void
    //    {
    //        $verifier = new AppUrlVerifier($this->connection, $this->shopIdProvider);
    //
    //        static::assertTrue($verifier->isAppUrlReachable());
    //    }

    //    public function testAppUrlReachableReturnsTrueIfRequestIsMadeToSameDomain(): void
    //    {
    //        $verifier = new AppUrlVerifier($this->connection, 'prod', false, $this->shopIdProvider);
    //
    //        $request = SymfonyRequest::create(EnvironmentHelper::getVariable('APP_URL') . '/api/_info/config');
    //
    //        static::assertTrue($verifier->isAppUrlReachable($request));
    //
    //        //        $request = $this->mockHandler->getLastRequest();
    //        //        static::assertNull($request);
    //    }

    public function testAppUrlReachableReturnsTrueIfAppUrlIsReachable(): void
    {
        $shopId = ShopId::v2(
            'shop-id',
            [],
            UrlVerificationStatus::newPassed()
        );

        $this->shopIdProvider->expects($this->once())
            ->method('getShopIdUnchecked')
            ->willReturn($shopId);

        $verifier = new AppUrlVerifier($this->connection, $this->shopIdProvider);

        static::assertTrue($verifier->isAppUrlReachable());
    }

    public function testAppUrlReachableReturnsFalseWhenNotReachable(): void
    {
        $shopId = ShopId::v2(
            'shop-id',
            [],
            UrlVerificationStatus::newFailed()
        );

        $this->shopIdProvider->expects($this->once())
            ->method('getShopIdUnchecked')
            ->willReturn($shopId);

        $verifier = new AppUrlVerifier($this->connection, $this->shopIdProvider);

        static::assertFalse($verifier->isAppUrlReachable());
    }

    public function testAppsThatNeedAppUrlReturnFalseWithoutAppsThatRequireRegistration(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('0');

        $verifier = new AppUrlVerifier($this->connection, $this->shopIdProvider);

        static::assertFalse($verifier->hasAppsThatNeedAppUrl());
    }

    public function testAppsThatNeedAppUrlReturnTrueWithAppsThatRequireRegistration(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('1');

        $verifier = new AppUrlVerifier($this->connection, $this->shopIdProvider);

        static::assertTrue($verifier->hasAppsThatNeedAppUrl());
    }
}
