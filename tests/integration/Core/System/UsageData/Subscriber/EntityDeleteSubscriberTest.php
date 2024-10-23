<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\UsageData\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('data-services')]
class EntityDeleteSubscriberTest extends TestCase
{
    use ClockSensitiveTrait;
    use IntegrationTestBehaviour;

    private Connection $connection;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        /** @var MockHttpClient $client */
        $client = $this->getContainer()->get('shopware.usage_data.gateway.client');
        $client->setResponseFactory(function (string $method, string $url): ResponseInterface {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            return new MockResponse();
        });
    }

    public function testHandleDeleteEventWritesSinglePrimaryKeyToDatabase(): void
    {
        static::mockTime(new \DateTimeImmutable('2023-08-30 00:00:00.000'));

        $this->systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, ConsentState::ACCEPTED->value);

        $productIds = new IdsCollection();

        $productBuilder = new ProductBuilder($productIds, 'product-to-delete', 1);
        $productBuilder->price(3.14);

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        $productRepository->create([$productBuilder->build()], Context::createDefaultContext());

        $productRepository->delete([['id' => $productIds->get('product-to-delete')]], Context::createDefaultContext());

        $result = $this->connection->executeQuery('SELECT `entity_name`, `entity_ids`, `deleted_at` FROM `usage_data_entity_deletion`')->fetchAssociative();

        static::assertIsArray($result);
        static::assertArrayHasKey('entity_ids', $result);
        static::assertIsString($result['entity_ids']);

        $result['entity_ids'] = \json_decode($result['entity_ids'], true, flags: \JSON_THROW_ON_ERROR);

        static::assertEquals([
            'entity_name' => 'product',
            'entity_ids' => ['id' => $productIds->get('product-to-delete')],
            'deleted_at' => '2023-08-30 00:00:00.000',
        ], $result);
    }

    public function testDoesNotTriggerWhenDeletingNonLiveVersionSinglePrimaryKey(): void
    {
        static::mockTime(new \DateTimeImmutable('2023-08-30 00:00:00.000'));

        $this->systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, ConsentState::REQUESTED->value);

        $productIds = new IdsCollection();

        $productBuilder = new ProductBuilder($productIds, 'product-to-delete', 1);
        $productBuilder->price(3.14);
        $product = $productBuilder->build();
        // non live version should not trigger the subscriber
        $product['versionId'] = Uuid::randomHex();

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        $productRepository->create([$product], Context::createDefaultContext());

        $productRepository->delete([['id' => $productIds->get('product-to-delete')]], Context::createDefaultContext());

        $result = $this->connection->executeQuery('SELECT `entity_name`, `entity_ids`, `deleted_at` FROM `usage_data_entity_deletion`')->fetchAssociative();

        // no product should have been written to the DB as no live version was deleted
        static::assertFalse($result);
    }

    public function testDoesNotTriggerWhenDeletingNonLiveVersionCombinedPrimaryKeys(): void
    {
        static::mockTime(new \DateTimeImmutable('2023-08-30 00:00:00.000'));

        $this->systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, ConsentState::REQUESTED->value);

        $idsCollection = new IdsCollection();

        // at least one has no live version --> should not trigger the subscriber
        $product = $this->insertTestProduct($idsCollection, Uuid::randomHex());
        $category = $this->insertTestCategory($idsCollection, Defaults::LIVE_VERSION);

        /** @var EntityRepository $productCategoryRepository */
        $productCategoryRepository = $this->getContainer()->get('product_category.repository');
        $productCategoryRepository->create([
            [
                'productId' => $product['id'],
                'productVersionId' => $product['versionId'],
                'categoryId' => $category['id'],
                'categoryVersionId' => $category['versionId'],
            ],
        ], Context::createDefaultContext());

        $productCategoryRepository->delete([
            [
                'productId' => $product['id'],
                'categoryId' => $category['id'],
            ],
        ], Context::createDefaultContext());

        $result = $this->connection->executeQuery('SELECT `entity_name`, `entity_ids`, `deleted_at` FROM `usage_data_entity_deletion`')->fetchAssociative();
        static::assertFalse($result);
    }

    public function testHandleDeleteEventWritesCombinedPrimaryKeysToDatabase(): void
    {
        static::mockTime(new \DateTimeImmutable('2023-08-30 00:00:00.000'));

        $this->systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, ConsentState::ACCEPTED->value);

        $userId = Uuid::randomHex();
        $userData = [
            'id' => $userId,
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'foobar',
            'localeId' => $this->getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
        ];

        /** @var EntityRepository $userRepository */
        $userRepository = $this->getContainer()->get('user.repository');
        $userRepository->create([$userData], Context::createDefaultContext());

        $aclRoleId = Uuid::randomHex();
        $aclRoleData = [
            'id' => $aclRoleId,
            'name' => 'foobar',
            'privileges' => [
                'user:read',
                'user:create',
                'user:update',
                'user:delete',
            ],
        ];

        $aclRoleRepository = $this->getContainer()->get('acl_role.repository');
        $aclRoleRepository->create([$aclRoleData], Context::createDefaultContext());

        /** @var EntityRepository $aclUserRoleRepository */
        $aclUserRoleRepository = $this->getContainer()->get('acl_user_role.repository');
        $aclUserRoleRepository->create([
            [
                'userId' => $userId,
                'aclRoleId' => $aclRoleId,
            ],
        ], Context::createDefaultContext());

        $aclUserRoleRepository->delete([
            [
                'userId' => $userId,
                'aclRoleId' => $aclRoleId,
            ],
        ], Context::createDefaultContext());

        $result = $this->connection->executeQuery('SELECT `entity_name`, `entity_ids`, `deleted_at` FROM `usage_data_entity_deletion`')->fetchAssociative();
        static::assertIsArray($result);
        static::assertArrayHasKey('entity_ids', $result);
        static::assertIsString($result['entity_ids']);

        $result['entity_ids'] = \json_decode($result['entity_ids'], true, flags: \JSON_THROW_ON_ERROR);

        static::assertEquals([
            'entity_name' => 'acl_user_role',
            'entity_ids' => [
                'userId' => $userId,
                'aclRoleId' => $aclRoleId,
            ],
            'deleted_at' => '2023-08-30 00:00:00.000',
        ], $result);
    }

    /**
     * @return array<mixed>
     */
    private function insertTestProduct(IdsCollection $idsCollection, string $versionId): array
    {
        $productBuilder = new ProductBuilder($idsCollection, 'product-1', 1);
        $productBuilder->price(3.14);

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        $product = $productBuilder->build();
        $product['versionId'] = $versionId;

        $productRepository->create([$product], Context::createDefaultContext());

        return $product;
    }

    /**
     * @return array{id: string, name: string, versionId: string}
     */
    private function insertTestCategory(IdsCollection $idsCollection, string $versionId): array
    {
        $categoryRepository = $this->getContainer()->get('category.repository');

        $category = [
            'id' => $idsCollection->get('category-1'),
            'name' => 'category',
            'versionId' => $versionId,
        ];

        $categoryRepository->create([$category], Context::createDefaultContext());

        return $category;
    }
}
