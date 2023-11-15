<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\UsageData\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Core\System\UsageData\Services\IntegrationChangedService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\System\UsageData\Subscriber\EntityDeleteSubscriber;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 *
 * @phpstan-import-type SystemConfigIntegration from ConsentService
 */
class IntegrationChangedServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testShopIdChanged(): void
    {
        $shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
        $currentShopId = $shopIdProvider->getShopId();
        $newShopId = Uuid::randomHex();

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $consentService = $this->getContainer()->get(ConsentService::class);
        $consentService->acceptConsent();

        /** @var SystemConfigIntegration $oldIntegration */
        $oldIntegration = $systemConfigService->get(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION);
        static::assertSame($currentShopId, $oldIntegration['shopId']);

        /** @var MockHttpClient $client */
        $client = $this->getContainer()->get('shopware.usage_data.gateway.client');
        $client->setResponseFactory(function ($method, $url, $options) use ($newShopId) {
            static::assertArrayHasKey('headers', $options);
            $headers = $options['headers'];
            $headers = array_filter($headers, fn ($header) => str_contains($header, 'Shopware-Shop-Id:'));
            static::assertCount(1, $headers);
            static::assertContains($newShopId, $headers[0]);

            static::assertArrayHasKey('body', $options);
            static::assertIsString($options['body']);
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('shop_id', $body);
            static::assertSame($newShopId, $body['shop_id']);

            static::assertArrayHasKey('consent_state', $body);
            static::assertSame(ConsentState::REVOKED->value, $body['consent_state']);

            return new MockResponse('', ['http_code' => 200]);
        });

        // change shop id in system config
        $systemConfigService->set(\Shopware\Core\Framework\App\ShopId\ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => EnvironmentHelper::getVariable('APP_URL'),
            'value' => $newShopId,
        ]);

        $this->setLastRunDateForEntities();

        // disable data push
        $systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED, true);

        $userCount = 10;
        $this->createAndHideBannerForUsers($userCount);

        $this->insertDeletions();

        $service = $this->getContainer()->get(IntegrationChangedService::class);
        $service->checkAndHandleIntegrationChanged();

        $this->checkThatIntegrationWasRemoved($oldIntegration);
        $this->checkConsentStateWasRemoved();
        $this->assertLastRunDateIsNull();
        $this->assertDataPushIsEnabled();
        $this->assertBannerIsShownForAllUsers($userCount);
        $this->assertDeletionTableIsEmpty();
    }

    private function createAndHideBannerForUsers(int $userCount): void
    {
        /** @var EntityRepository $userRepository */
        $userRepository = $this->getContainer()->get('user.repository');

        $consentService = $this->getContainer()->get(ConsentService::class);
        for ($i = 0; $i < $userCount; ++$i) {
            $userRepository->create([[
                'id' => $userId = Uuid::randomHex(),
                'username' => Uuid::randomHex(),
                'firstName' => Uuid::randomHex(),
                'lastName' => Uuid::randomHex(),
                'email' => Uuid::randomHex(),
                'password' => 'shopware',
                'locale' => [
                    'code' => Uuid::randomHex(),
                    'name' => 'testLocale',
                    'territory' => 'somewhere',
                ],
            ]], Context::createDefaultContext());

            $consentService->hideConsentBannerForUser($userId, Context::createDefaultContext());
        }

        /** @var EntityRepository $userConfigRepository */
        $userConfigRepository = $this->getContainer()->get('user_config.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigs = $userConfigRepository->search($criteria, Context::createDefaultContext());
        static::assertSame($userCount, $userConfigs->getTotal());
    }

    private function insertDeletions(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->insert(EntityDeleteSubscriber::DELETIONS_TABLE_NAME, [
            'id' => Uuid::randomBytes(),
            'entity_name' => 'test',
            'entity_ids' => json_encode(['test' => 'test'], \JSON_THROW_ON_ERROR),
            'deleted_at' => (new \DateTimeImmutable('now'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function assertDeletionTableIsEmpty(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->from(EntityDeleteSubscriber::DELETIONS_TABLE_NAME);
        $queryBuilder->select('COUNT(*) AS count');
        $result = $queryBuilder->executeQuery()->fetchAssociative();

        static::assertIsArray($result);
        static::assertArrayHasKey('count', $result);
        static::assertSame('0', $result['count']);
    }

    private function assertBannerIsShownForAllUsers(int $userCount): void
    {
        /** @var EntityRepository $userConfigRepository */
        $userConfigRepository = $this->getContainer()->get('user_config.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigs = $userConfigRepository->search($criteria, Context::createDefaultContext());
        static::assertSame($userCount, $userConfigs->getTotal());

        /** @var UserConfigEntity $userConfig */
        foreach ($userConfigs->getElements() as $userConfig) {
            $value = $userConfig->getValue();
            static::assertIsArray($value);
            static::assertArrayHasKey('_value', $value);
            static::assertFalse($value['_value']);
        }
    }

    private function setLastRunDateForEntities(): void
    {
        $entityDefinitionService = $this->getContainer()->get(EntityDefinitionService::class);
        $appConfig = $this->getContainer()->get(AbstractKeyValueStorage::class);

        foreach ($entityDefinitionService->getAllowedEntityDefinitions() as $definition) {
            $appConfig->set(
                EntityDispatchService::getLastRunKeyForEntity($definition->getEntityName()),
                (new \DateTimeImmutable('now'))->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            );
        }
    }

    private function assertLastRunDateIsNull(): void
    {
        $entityDefinitionService = $this->getContainer()->get(EntityDefinitionService::class);
        $appConfig = $this->getContainer()->get(AbstractKeyValueStorage::class);

        foreach ($entityDefinitionService->getAllowedEntityDefinitions() as $definition) {
            $result = $appConfig->get(EntityDispatchService::getLastRunKeyForEntity($definition->getEntityName()));
            static::assertNull($result);
        }
    }

    private function assertDataPushIsEnabled(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        static::assertFalse($systemConfigService->get(ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED));
    }

    /**
     * @param SystemConfigIntegration $oldIntegration
     */
    private function checkThatIntegrationWasRemoved(array $oldIntegration): void
    {
        static::assertNotNull($oldIntegration['integrationId']);
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        /** @var SystemConfigIntegration $newIntegration */
        $newIntegration = $systemConfigService->get(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION);
        static::assertNull($newIntegration);

        /** @var EntityRepository $integrationRepository */
        $integrationRepository = $this->getcontainer()->get('integration.repository');
        $integrationSearchResult = $integrationRepository->search(
            new Criteria([$oldIntegration['integrationId']]),
            Context::createDefaultContext()
        );
        static::assertSame(0, $integrationSearchResult->getTotal());
    }

    private function checkConsentStateWasRemoved(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        static::assertNull($systemConfigService->get(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE));
    }
}
