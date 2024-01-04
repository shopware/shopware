<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Consent\BannerService;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(BannerService::class)]
class BannerServiceTest extends TestCase
{
    public function testHasUserHiddenConsentBannerReturnsFalseIfNotSet(): void
    {
        $userId = '018a93bbe90570eda0d89c600de7dd19';
        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $userConfigRepository = new StaticEntityRepository([
            new UserConfigCollection([]),
        ]);

        $consentService = new BannerService(
            $userConfigRepository,
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
        $userConfig->setKey(BannerService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER);
        $userConfig->setValue([
            '_value' => true,
        ]);

        $userConfigRepository = new StaticEntityRepository([
            new UserConfigCollection([$userConfig]),
        ]);

        $consentService = new BannerService(
            $userConfigRepository
        );

        static::assertTrue($consentService->hasUserHiddenConsentBanner($userId, $context));
    }

    public function testHideConsentBannerForNotExistingUser(): void
    {
        $userId = Uuid::randomHex();
        $context = new Context(new AdminApiSource($userId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        $criteria->addFilter(new EqualsFilter('key', BannerService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigRepository = new StaticEntityRepository([
            new IdSearchResult(
                0,
                [],
                $criteria,
                Context::createDefaultContext(),
            ),
        ]);

        $consentService = new BannerService(
            $userConfigRepository
        );

        $consentService->hideConsentBannerForUser($userId, $context);

        $upsert = $userConfigRepository->upserts[0][0];
        unset($upsert['id']);

        static::assertSame([
            'userId' => $userId,
            'key' => BannerService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER,
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
        $criteria->addFilter(new EqualsFilter('key', BannerService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigRepository = new StaticEntityRepository([
            new IdSearchResult(
                1,
                [['data' => [], 'primaryKey' => $primaryKeyUserConfig]],
                $criteria,
                Context::createDefaultContext(),
            ),
        ]);

        $consentService = new BannerService(
            $userConfigRepository
        );

        $consentService->hideConsentBannerForUser($userId, $context);

        static::assertSame([
            'id' => $primaryKeyUserConfig,
            'userId' => $userId,
            'key' => BannerService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER,
            'value' => [
                '_value' => true,
            ],
        ], $userConfigRepository->upserts[0][0]);
    }

    public function testResetIsBannerHiddenForAllUsers(): void
    {
        $idsCollection = new IdsCollection();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', BannerService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));
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
                'key' => BannerService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER,
                'value' => ['_value' => false],
            ];
        }

        $userConfigRepository->expects(static::once())
            ->method('upsert')
            ->with($updates, Context::createDefaultContext());

        $consentService = new BannerService(
            $userConfigRepository
        );

        $consentService->resetIsBannerHiddenForAllUsers();
    }

    public function testEarlyReturnResetIsBannerHiddenIfNoUserConfigsGiven(): void
    {
        $idsCollection = new IdsCollection();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', BannerService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));
        $context = Context::createDefaultContext();

        $emptyUserConfigCollection = $this->createUserConfigEntities($idsCollection, 0);

        $userConfigRepository = $this->createMock(EntityRepository::class);
        $userConfigRepository->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('user_config', 0, $emptyUserConfigCollection, null, $criteria, $context));

        $userConfigRepository->expects(static::never())
            ->method('upsert');

        $consentService = new BannerService(
            $userConfigRepository
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
