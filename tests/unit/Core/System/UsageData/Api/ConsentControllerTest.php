<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Api\ConsentController;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\UsageDataException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
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
            new StaticEntityRepository([]),
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
            new StaticEntityRepository([]),
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
            new StaticEntityRepository([]),
        );

        static::expectException(UsageDataException::class);
        $controller->getConsent(Context::createDefaultContext(new AdminApiSource(null, '018a93bbe90570eda0d89c600de7dd19')));
    }

    public function testDelegatesConsentAcceptance(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('acceptConsent');

        $controller = new ConsentController(
            $consentService,
            new StaticEntityRepository([]),
        );

        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        $controller->acceptConsent($context);
    }

    public function testDelegatesConsentRevocation(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('revokeConsent');

        $controller = new ConsentController(
            $consentService,
            new StaticEntityRepository([]),
        );

        $context = Context::createDefaultContext(new AdminApiSource('018a93bbe90570eda0d89c600de7dd19'));

        $controller->revokeConsent($context);
    }

    public function testCreatesUserConfigIfConsentBannerShouldBeHidden(): void
    {
        $userId = '018a93bbe90570eda0d89c600de7dd19';

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        $criteria->addFilter(new EqualsFilter('key', ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigRepository = new StaticEntityRepository([
            new IdSearchResult(
                0,
                [],
                $criteria,
                Context::createDefaultContext(),
            ),
        ]);

        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $controller = new ConsentController(
            $this->createMock(ConsentService::class),
            $userConfigRepository,
        );

        $controller->hideConsentBanner($context);

        $userConfig = $userConfigRepository->upserts[0][0];

        static::assertArrayHasKey('id', $userConfig);
        unset($userConfig['id']);

        static::assertEquals([
            'userId' => '018a93bbe90570eda0d89c600de7dd19',
            'key' => 'core.usageData.hideConsentBanner',
            'value' => ['_value' => true],
        ], $userConfig);
    }

    public function testUpdatesUserConfigIfConsentBannerShouldBeHidden(): void
    {
        $userId = '018a93bbe90570eda0d89c600de7dd19';
        $userConfigId = '1b805e90e74f4981b60ef05e0af734ee';

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        $criteria->addFilter(new EqualsFilter('key', ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigRepository = new StaticEntityRepository([
            new IdSearchResult(
                1,
                [['data' => $userConfigId, 'primaryKey' => $userConfigId]],
                $criteria,
                Context::createDefaultContext(),
            ),
        ]);

        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $controller = new ConsentController(
            $this->createMock(ConsentService::class),
            $userConfigRepository,
        );

        $controller->hideConsentBanner($context);

        $userConfig = $userConfigRepository->upserts[0][0];
        static::assertEquals([
            'id' => $userConfigId,
            'userId' => $userId,
            'key' => 'core.usageData.hideConsentBanner',
            'value' => ['_value' => true],
        ], $userConfig);
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
            new StaticEntityRepository([]),
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
            new StaticEntityRepository([]),
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
            new StaticEntityRepository([]),
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
}
