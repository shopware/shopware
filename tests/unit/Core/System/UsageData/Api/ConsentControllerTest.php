<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Api\ConsentController;
use Shopware\Core\System\UsageData\Consent\ConsentReporter;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\System\UsageData\UsageDataException;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Api\ConsentController
 */
#[Package('merchant-services')]
class ConsentControllerTest extends TestCase
{
    public function testGetConsentReturnsStateFromDetector(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')->willReturn(true);
        $consentService->method('hasUserHiddenConsentBanner')->willReturn(true);

        $controller = new ConsentController(
            $consentService,
        );

        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        static::assertEquals(
            [
                'isConsentGiven' => true,
                'isBannerHidden' => true,
            ],
            $this->getJsonResponseResult($controller->getConsent($context))
        );
    }

    public function testGetConsentMustBeCalledWithAdminApiSource(): void
    {
        $controller = new ConsentController(
            $this->createMock(ConsentService::class),
        );

        static::expectException(UsageDataException::class);
        static::expectExceptionMessage(sprintf(
            'Expected context source to be "%s" but got "%s".',
            AdminApiSource::class,
            SystemSource::class
        ));
        $controller->getConsent(Context::createDefaultContext());
    }

    public function testGetConsentMustBeCalledFromAUser(): void
    {
        $controller = new ConsentController(
            $this->createMock(ConsentService::class),
        );

        static::expectException(UsageDataException::class);
        $controller->getConsent(Context::createDefaultContext(new AdminApiSource(null, '018a93bbe90570eda0d89c600de7dd19')));
    }

    public function testDelegatesConsentAcceptance(): void
    {
        $consentService = $this->getConsentService();

        $controller = new ConsentController(
            $consentService,
        );

        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        $controller->acceptConsent($context);
        static::assertTrue($consentService->isConsentAccepted());
    }

    public function testDelegatesConsentRevocation(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('revokeConsent');

        $controller = new ConsentController(
            $consentService,
        );

        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        $controller->revokeConsent($context);
    }

    public function testHidesConsentBannerForSpecificUser(): void
    {
        $userId = Uuid::randomHex();
        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('hideConsentBannerForUser')
            ->with($userId, $context);

        $controller = new ConsentController($consentService);
        $response = $controller->hideConsentBanner($context);

        static::assertSame(204, $response->getStatusCode());
    }

    public function testCatchesConsentAlreadyRequestedException(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('requestConsent')
            ->willThrowException(UsageDataException::consentAlreadyRequested());

        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        $controller = new ConsentController(
            $consentService,
        );

        $controller->getConsent($context);
    }

    public function testCatchesConsentAlreadyAcceptedException(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('acceptConsent')
            ->willThrowException(UsageDataException::consentAlreadyAccepted());

        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        $controller = new ConsentController(
            $consentService,
        );

        $controller->acceptConsent($context);
    }

    public function testCatchesConsentAlreadyRevokedException(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('revokeConsent')
            ->willThrowException(UsageDataException::consentAlreadyRevoked());

        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        $controller = new ConsentController(
            $consentService,
        );

        $controller->revokeConsent($context);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonResponseResult(JsonResponse $response): array
    {
        $json = $response->getContent();
        static::assertIsString($json);

        return json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
    }

    private function getConsentService(): ConsentService
    {
        return new ConsentService(
            $this->getSystemConfigService(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'test-shop.com',
        );
    }

    private function getSystemConfigService(): StaticSystemConfigService
    {
        return new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED => false,
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REQUESTED->value,
        ]);
    }
}
