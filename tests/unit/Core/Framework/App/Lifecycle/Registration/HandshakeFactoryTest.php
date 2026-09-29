<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Registration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Registration\HandshakeFactory;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\App\Url\VerificationState;
use Shopware\Core\Framework\App\Url\VerificationStatus;
use Shopware\Core\Framework\App\Validation\Requirements\SecureUrlValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HandshakeFactory::class)]
class HandshakeFactoryTest extends TestCase
{
    private Manifest $manifest;

    protected function setUp(): void
    {
        $this->manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/manifest.xml');
    }

    public function testRegistersWithTheShopIdOnceTheAppUrlIsVerified(): void
    {
        $shopId = ShopId::v2('verified-shop-id');

        $appUrlVerifier = $this->createMock(AppUrlVerifier::class);
        $appUrlVerifier->expects($this->once())
            ->method('forceVerify')
            ->with($shopId)
            ->willReturn(new VerificationState(VerificationStatus::PASS, 1, new \DateTimeImmutable()));

        $request = $this->createFactory($shopId, $appUrlVerifier, self::publicTarget())
            ->create($this->manifest, new AppEntity())
            ->assembleRequest();

        static::assertStringContainsString('shop-id=verified-shop-id', $request->getUri()->getQuery());
    }

    public function testFailsTheRegistrationWhenTheAppUrlFailedVerification(): void
    {
        $appUrlVerifier = static::createStub(AppUrlVerifier::class);
        $appUrlVerifier->method('forceVerify')->willReturn(
            new VerificationState(VerificationStatus::HARD_FAIL, 1, new \DateTimeImmutable(), 'APP_URL is invalid: HTTPS is required.')
        );

        $this->expectExceptionObject(AppException::registrationFailed(
            'test',
            'APP_URL "http://shop.example" is incorrect or does not reach this installation (APP_URL is invalid: HTTPS is required.)',
        ));

        $this->createFactory(ShopId::v2('shop-id'), $appUrlVerifier, self::publicTarget())->create($this->manifest, new AppEntity());
    }

    public function testFailsTheRegistrationWhenTheAppUrlVerificationSoftFailed(): void
    {
        $appUrlVerifier = static::createStub(AppUrlVerifier::class);
        $appUrlVerifier->method('forceVerify')->willReturn(
            new VerificationState(VerificationStatus::SOFT_FAIL, 1, new \DateTimeImmutable(), 'Failed to connect to APP_URL: timeout')
        );

        $this->expectExceptionObject(AppException::registrationFailed(
            'test',
            'APP_URL "http://shop.example" is incorrect or does not reach this installation (Failed to connect to APP_URL: timeout)',
        ));

        $this->createFactory(ShopId::v2('shop-id'), $appUrlVerifier, self::publicTarget())->create($this->manifest, new AppEntity());
    }

    public function testDoesNotVerifyTheAppUrlForAPrivateRegistrationTarget(): void
    {
        $appUrlVerifier = $this->createMock(AppUrlVerifier::class);
        $appUrlVerifier->expects($this->never())->method('forceVerify');

        $request = $this->createFactory(ShopId::v2('shop-id'), $appUrlVerifier, new SecureUrlValidator(static fn (): array => [['ip' => '127.0.0.1']]))
            ->create($this->manifest, new AppEntity())
            ->assembleRequest();

        static::assertStringContainsString('shop-id=shop-id', $request->getUri()->getQuery());
    }

    private static function publicTarget(): SecureUrlValidator
    {
        return new SecureUrlValidator(static fn (): array => [['ip' => '93.184.215.14']]);
    }

    private function createFactory(ShopId $shopId, AppUrlVerifier $appUrlVerifier, SecureUrlValidator $secureUrlValidator): HandshakeFactory
    {
        $shopIdProvider = static::createStub(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')->willReturn($shopId);

        return new HandshakeFactory(
            'http://shop.example',
            $shopIdProvider,
            static::createStub(StoreClient::class),
            '6.7.0.0',
            new MockClock(),
            $appUrlVerifier,
            $secureUrlValidator,
        );
    }
}
