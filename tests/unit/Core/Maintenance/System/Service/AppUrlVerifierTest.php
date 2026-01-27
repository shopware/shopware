<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerifier as CoreAppUrlVerifier;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;

/**
 * @internal
 */
#[CoversClass(AppUrlVerifier::class)]
class AppUrlVerifierTest extends TestCase
{
    private Connection&MockObject $connection;

    private ShopIdProvider&MockObject $shopIdProvider;

    private CoreAppUrlVerifier&MockObject $appUrlVerifier;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->appUrlVerifier = $this->createMock(CoreAppUrlVerifier::class);
    }

    public function testAppUrlReachableReturnsTrueIfAppUrlIsReachable(): void
    {
        $shopId = ShopId::v2(
            'shop-id'
        );

        $this->appUrlVerifier->expects($this->once())
            ->method('getCurrentState')
            ->willReturn(null);

        $this->shopIdProvider->expects($this->once())
            ->method('getShopId')
            ->willReturn($shopId);

        $this->appUrlVerifier->expects($this->once())
            ->method('forceVerify')
            ->with($shopId)
            ->willReturn(true);

        $verifier = new AppUrlVerifier(
            $this->connection,
            $this->shopIdProvider,
            $this->appUrlVerifier,
        );

        static::assertTrue($verifier->isAppUrlReachable());
    }

    public function testAppUrlReachableReturnsFalseWhenNotReachable(): void
    {
        $shopId = ShopId::v2(
            'shop-id'
        );

        $this->appUrlVerifier->expects($this->once())
            ->method('getCurrentState')
            ->willReturn(null);

        $this->shopIdProvider->expects($this->once())
            ->method('getShopId')
            ->willReturn($shopId);

        $this->appUrlVerifier->expects($this->once())
            ->method('forceVerify')
            ->with($shopId)
            ->willReturn(false);

        $verifier = new AppUrlVerifier(
            $this->connection,
            $this->shopIdProvider,
            $this->appUrlVerifier,
        );

        static::assertFalse($verifier->isAppUrlReachable());
    }

    public function testAppsThatNeedAppUrlReturnFalseWithoutAppsThatRequireRegistration(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('0');

        $verifier = new AppUrlVerifier(
            $this->connection,
            $this->shopIdProvider,
            $this->appUrlVerifier,
        );

        static::assertFalse($verifier->hasAppsThatNeedAppUrl());
    }

    public function testAppsThatNeedAppUrlReturnTrueWithAppsThatRequireRegistration(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('1');

        $verifier = new AppUrlVerifier(
            $this->connection,
            $this->shopIdProvider,
            $this->appUrlVerifier,
        );

        static::assertTrue($verifier->hasAppsThatNeedAppUrl());
    }
}
