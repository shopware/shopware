<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Consent;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentReporter;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyAcceptedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRequestedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRevokedException;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Consent\ConsentService
 */
#[Package('merchant-services')]
class ConsentServiceTest extends TestCase
{
    public function testIsApprovalGivenReturnsConfigValue(): void
    {
        $systemConfig = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
        ]);

        $consentService = new ConsentService(
            $systemConfig,
            new StaticEntityRepository([]),
            new StaticEntityRepository([]),
            new StaticEntityRepository([]),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::assertTrue($consentService->isConsentAccepted());

        $systemConfig->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, false);

        static::assertFalse($consentService->isConsentAccepted());
    }

    public function testIsApprovalGivenIsFalseIfConfigValueIsNotSet(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService(),
            new StaticEntityRepository([]),
            new StaticEntityRepository([]),
            new StaticEntityRepository([]),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::assertFalse($consentService->isConsentAccepted());
    }

    public function testThrowsIfConsentHasAlreadyBeenRequested(): void
    {
        $consentReporter = $this->createMock(ConsentReporter::class);
        $consentReporter->expects(static::never())
            ->method('reportConsent');

        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REQUESTED->value,
            ]),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $consentReporter,
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::expectException(ConsentAlreadyRequestedException::class);
        $consentService->requestConsent();
    }

    public function testStoresAndReportsConsentStateWhenRequestedForTheFirstTime(): void
    {
        $consentReporter = $this->createMock(ConsentReporter::class);
        $consentReporter->expects(static::once())
            ->method('reportConsent')
            ->with(ConsentState::REQUESTED);

        $systemConfigService = new StaticSystemConfigService();

        $consentService = new ConsentService(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $consentReporter,
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        $consentService->requestConsent();

        static::assertSame(
            $systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE),
            ConsentState::REQUESTED->value
        );
    }

    public function testStoresAndReportsConsentStateWithAccessKeysWhenAccepted(): void
    {
        $consentReporter = $this->createMock(ConsentReporter::class);
        $consentReporter->expects(static::once())
            ->method('reportConsent')
            ->with(ConsentState::ACCEPTED, static::callback(static function (array $accessKeys): bool {
                static::assertArrayHasKey('accessKey', $accessKeys);
                static::assertIsString($accessKeys['accessKey']);
                static::assertNotEmpty($accessKeys['accessKey']);

                static::assertArrayHasKey('secretAccessKey', $accessKeys);
                static::assertIsString($accessKeys['secretAccessKey']);
                static::assertNotEmpty($accessKeys['secretAccessKey']);

                return true;
            }));

        $systemConfigService = new StaticSystemConfigService();

        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects(static::once())
            ->method('create');

        $consentService = new ConsentService(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $integrationRepository,
            $consentReporter,
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        $consentService->acceptConsent();

        static::assertSame(
            $systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE),
            ConsentState::ACCEPTED->value
        );
    }

    public function testStoresAndReportsConsentStateAndRemovesIntegrationWhenRevoked(): void
    {
        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects(static::once())
            ->method('delete')
            ->with([['id' => 'integration-id']]);

        $consentReporter = $this->createMock(ConsentReporter::class);
        $consentReporter->expects(static::once())
            ->method('reportConsent')
            ->with(ConsentState::REVOKED);

        $systemConfigService = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION => [
                'integrationId' => 'integration-id',
                'appUrl' => 'APP_URL',
            ],
        ]);

        $consentService = new ConsentService(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $integrationRepository,
            $consentReporter,
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        $consentService->revokeConsent();

        static::assertSame(
            $systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE),
            ConsentState::REVOKED->value
        );
    }

    public function testDoesNotReportConsentStateChangeIfStateIsTheSameAsBefore(): void
    {
        $consentReporter = $this->createMock(ConsentReporter::class);
        $consentReporter->expects(static::never())
            ->method('reportConsent');

        $systemConfigService = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
        ]);

        $consentService = new ConsentService(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $consentReporter,
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::expectException(ConsentAlreadyAcceptedException::class);
        $consentService->acceptConsent();
    }

    public function testIgnoresExceptionsDuringReportingConsentState(): void
    {
        $consentReporter = $this->createMock(ConsentReporter::class);
        $consentReporter->expects(static::once())
            ->method('reportConsent')
            ->willThrowException(new \Exception());

        $systemConfigService = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
        ]);

        $consentService = new ConsentService(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $consentReporter,
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        $consentService->revokeConsent();

        static::assertSame(
            ConsentState::REVOKED->value,
            $systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE),
        );
    }

    public function testHasNoConsentState(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::assertFalse($consentService->hasConsentState());
    }

    public function testHasConsentState(): void
    {
        foreach (ConsentState::cases() as $consentState) {
            $consentService = new ConsentService(
                new StaticSystemConfigService([
                    ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => $consentState->value,
                ]),
                $this->createMock(EntityRepository::class),
                $this->createMock(EntityRepository::class),
                $this->createMock(EntityRepository::class),
                $this->createMock(ConsentReporter::class),
                $this->createMock(ShopIdProvider::class),
                new MockClock(),
                'APP_URL',
            );

            static::assertTrue($consentService->hasConsentState());
        }
    }

    public function testConsentIsAccepted(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
            ]),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::assertTrue($consentService->isConsentAccepted());
    }

    public function testHasUserHiddenConsentBannerReturnsFalseIfNotSet(): void
    {
        $userId = '018a93bbe90570eda0d89c600de7dd19';
        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $userConfigRepository = new StaticEntityRepository([
            new UserConfigCollection([]),
        ]);

        $consentService = new ConsentService(
            new StaticSystemConfigService(),
            $this->createMock(EntityRepository::class),
            $userConfigRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::assertFalse($consentService->hasUserHiddenConsentBanner($userId, $context));
    }

    public function testHasUserHiddenConsentBannerReturnsUserConfig(): void
    {
        $userId = '018a93bbe90570eda0d89c600de7dd19';
        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $userConfig = new UserConfigEntity();
        $userConfig->setId('018a93bc7386721aaa4f372bbed53d73');
        $userConfig->setUniqueIdentifier('018a93bc7386721aaa4f372bbed53d73');
        $userConfig->setKey(ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER);
        $userConfig->setValue([
            '_value' => true,
        ]);

        $userConfigRepository = new StaticEntityRepository([
            new UserConfigCollection([$userConfig]),
        ]);

        $consentService = new ConsentService(
            new StaticSystemConfigService(),
            $this->createMock(EntityRepository::class),
            $userConfigRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::assertTrue($consentService->hasUserHiddenConsentBanner($userId, $context));
    }

    public function testThrowsIfConsentIsAlreadyRevoked(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REVOKED->value,
            ]),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::expectException(ConsentAlreadyRevokedException::class);
        $consentService->revokeConsent();
    }

    public function testIgnoresEntityNotFoundExceptionWhenRevokingConsent(): void
    {
        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects(static::once())
            ->method('delete')
            ->willThrowException(new EntityNotFoundException('integration', 'id'));

        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
                ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION => [
                    'integrationId' => 'id',
                    'appUrl' => 'APP_URL',
                ],
            ]),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $integrationRepository,
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        $consentService->revokeConsent();
    }

    public function testGetLastApprovalDateReturnsCurrentDateTime(): void
    {
        $systemConfig = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
        ]);

        $currentTime = new \DateTimeImmutable();

        $consentService = new ConsentService(
            $systemConfig,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock($currentTime),
            'APP_URL',
        );

        static::assertEquals($currentTime, $consentService->getLastConsentIsAcceptedDate());
    }

    public function testGetLastApprovalDateReturnsNullWhenApprovalWasNeverGiven(): void
    {
        $systemConfig = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REQUESTED->value,
        ]);

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('first')
            ->willReturn(null);

        $systemConfigRepository = $this->createMock(EntityRepository::class);
        $systemConfigRepository->method('search')
            ->willReturn($entitySearchResult);

        $consentService = new ConsentService(
            $systemConfig,
            $systemConfigRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::assertNull($consentService->getLastConsentIsAcceptedDate());
    }

    public function testGetLastApprovalDateReturnsLastSystemConfigUpdatedAtDateTime(): void
    {
        $systemConfig = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REVOKED->value,
        ]);

        $updatedAt = new \DateTimeImmutable('2021-01-01 00:00:00');
        $systemConfigEntity = new SystemConfigEntity();
        $systemConfigEntity->setUpdatedAt($updatedAt);

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('first')
            ->willReturn($systemConfigEntity);

        $systemConfigRepository = $this->createMock(EntityRepository::class);
        $systemConfigRepository->method('search')
            ->willReturn($entitySearchResult);

        $consentService = new ConsentService(
            $systemConfig,
            $systemConfigRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::assertEquals($updatedAt, $consentService->getLastConsentIsAcceptedDate());
    }

    public function testUpdateConsentIntegrationAppUrl(): void
    {
        $consentReporter = $this->createMock(ConsentReporter::class);
        $consentReporter->expects(static::once())
            ->method('reportConsentIntegrationAppUrlChanged')
            ->with(
                'shopId',
                [
                    'accessKey' => 'accessKey',
                    'secretAccessKey' => 'secretAccessKey',
                    'appUrl' => 'APP_URL',
                ]
            );

        $consentService = new ConsentService(
            $this->createMock(SystemConfigService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $consentReporter,
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        $consentService->updateConsentIntegrationAppUrl(
            'shopId',
            [
                'accessKey' => 'accessKey',
                'secretAccessKey' => 'secretAccessKey',
                'appUrl' => 'APP_URL',
            ]
        );
    }

    public function testShouldPushDataReturnsFalse(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED => true,
            ]),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::assertFalse($consentService->shouldPushData());
    }

    public function testShouldPushDataReturnsTrue(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED => false,
            ]),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        static::assertTrue($consentService->shouldPushData());
    }

    public function testHideConsentBannerForNotExistingUser(): void
    {
        $userId = Uuid::randomHex();
        $context = new Context(new AdminApiSource($userId));

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

        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED => false,
            ]),
            $this->createMock(EntityRepository::class),
            $userConfigRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        $consentService->hideConsentBannerForUser($userId, $context);

        $upsert = $userConfigRepository->upserts[0][0];
        unset($upsert['id']);

        static::assertSame([
            'userId' => $userId,
            'key' => ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER,
            'value' => [
                '_value' => true,
            ],
        ], $upsert);
    }

    public function testHideConsentBannerForExistingUser(): void
    {
        $userId = Uuid::randomHex();
        $primaryKeyUserConfig = Uuid::randomHex();
        $context = new Context(new AdminApiSource($userId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        $criteria->addFilter(new EqualsFilter('key', ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigRepository = new StaticEntityRepository([
            new IdSearchResult(
                1,
                [['data' => [], 'primaryKey' => $primaryKeyUserConfig]],
                $criteria,
                Context::createDefaultContext(),
            ),
        ]);

        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED => false,
            ]),
            $this->createMock(EntityRepository::class),
            $userConfigRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        $consentService->hideConsentBannerForUser($userId, $context);

        static::assertSame([
            'id' => $primaryKeyUserConfig,
            'userId' => $userId,
            'key' => ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER,
            'value' => [
                '_value' => true,
            ],
        ], $userConfigRepository->upserts[0][0]);
    }

    public function testResetIsBannerHiddenForAllUsers(): void
    {
        $idsCollection = new IdsCollection();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));
        $context = Context::createDefaultContext();

        $userConfigCollection = $this->createUserConfigEntities($idsCollection, 10);

        $userConfigRepository = $this->createMock(EntityRepository::class);
        $userConfigRepository->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('user_config', $userConfigCollection->count(), $userConfigCollection, null, $criteria, $context));

        $updates = [];
        for ($i = 0; $i < $userConfigCollection->count(); ++$i) {
            $updates[] = [
                'id' => $idsCollection->get('userConfig-id-' . $i),
                'userId' => $idsCollection->get('userConfig-userId-' . $i),
                'key' => ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER,
                'value' => ['_value' => false],
            ];
        }

        $userConfigRepository->expects(static::once())
            ->method('upsert')
            ->with($updates, Context::createDefaultContext());

        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED => false,
            ]),
            $this->createMock(EntityRepository::class),
            $userConfigRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        $consentService->resetIsBannerHiddenForAllUsers();
    }

    public function testEarlyReturnResetIsBannerHiddenIfNoUserConfigsGiven(): void
    {
        $idsCollection = new IdsCollection();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));
        $context = Context::createDefaultContext();

        $emptyUserConfigCollection = $this->createUserConfigEntities($idsCollection, 0);

        $userConfigRepository = $this->createMock(EntityRepository::class);
        $userConfigRepository->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('user_config', 0, $emptyUserConfigCollection, null, $criteria, $context));

        $userConfigRepository->expects(static::never())
            ->method('upsert');

        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED => false,
            ]),
            $this->createMock(EntityRepository::class),
            $userConfigRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(ConsentReporter::class),
            $this->createMock(ShopIdProvider::class),
            new MockClock(),
            'APP_URL',
        );

        $consentService->resetIsBannerHiddenForAllUsers();
    }

    private function createUserConfigEntities(IdsCollection $idsCollection, int $count): UserConfigCollection
    {
        $collection = new UserConfigCollection();

        for ($i = 0; $i < $count; ++$i) {
            $userConfigEntity = new UserConfigEntity();
            $userConfigEntity->setUniqueIdentifier(Uuid::randomHex());
            $userConfigEntity->setId($idsCollection->get('userConfig-id-' . $i));
            $userConfigEntity->setUserId($idsCollection->get('userConfig-userId-' . $i));

            $collection->add($userConfigEntity);
        }

        return $collection;
    }
}
