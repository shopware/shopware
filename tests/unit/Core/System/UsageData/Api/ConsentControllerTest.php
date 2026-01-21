<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\ConsentScope\System;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\DTO\ConsentState as ConsentSystemConsentState;
use Shopware\Core\System\Consent\Service\ConsentService as ConsentSystemConsentService;
use Shopware\Core\System\UsageData\Api\ConsentController;
use Shopware\Core\System\UsageData\Consent\BannerService;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentController::class)]
class ConsentControllerTest extends TestCase
{
    public function testGetConsentReturnsStateFromService(): void
    {
        $consentSystemConsentService = $this->createMock(ConsentSystemConsentService::class);
        $consentSystemConsentService->method('getConsentState')->willReturn(new ConsentSystemConsentState(
            BackendData::NAME,
            System::NAME,
            'system',
            ConsentStatus::ACCEPTED,
            'admin-user-id',
            '2026-02-06 00:00:00'
        ));

        $bannerService = $this->createMock(BannerService::class);
        $bannerService->method('hasUserHiddenConsentBanner')->willReturn(true);

        $controller = new ConsentController(
            $consentSystemConsentService,
            $bannerService,
        );

        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        static::assertSame(
            [
                'isConsentGiven' => true,
                'isBannerHidden' => true,
            ],
            $this->getJsonResponseResult($controller->getConsent($context))
        );
    }

    public function testDelegatesConsentAcceptance(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        $consentService = $this->createMock(ConsentSystemConsentService::class);
        $consentService->expects($this->once())
            ->method('acceptConsent')
            ->with(BackendData::NAME, $context);

        $controller = new ConsentController(
            $consentService,
            $this->createMock(BannerService::class),
        );

        $controller->acceptConsent($context);
    }

    public function testDelegatesConsentRevocation(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        $consentService = $this->createMock(ConsentSystemConsentService::class);
        $consentService->expects($this->once())
            ->method('revokeConsent')
            ->with(BackendData::NAME, $context);

        $controller = new ConsentController(
            $consentService,
            $this->createMock(BannerService::class),
        );

        $controller->revokeConsent($context);
    }

    public function testHidesConsentBannerForSpecificUser(): void
    {
        $userId = Uuid::randomHex();
        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $bannerService = $this->createMock(BannerService::class);
        $bannerService->expects($this->once())
            ->method('hideConsentBannerForUser')
            ->with($userId, $context);

        $controller = new ConsentController(
            $this->createMock(ConsentSystemConsentService::class),
            $bannerService,
        );
        $response = $controller->hideConsentBanner($context);

        static::assertSame(204, $response->getStatusCode());
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
}
