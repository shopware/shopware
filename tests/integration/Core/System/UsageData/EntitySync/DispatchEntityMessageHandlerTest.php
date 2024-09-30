<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldType\DateInterval;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessageHandler;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('data-services')]
class DispatchEntityMessageHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $idsCollection;

    private Connection $connection;

    protected function setUp(): void
    {
        $client = $this->getMockHttpClient();
        $client->setResponseFactory(function (string $method, string $url): ResponseInterface {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            return new MockResponse();
        });

        $this->idsCollection = new IdsCollection();
        $this->connection = $this->getContainer()->get(Connection::class);

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, ConsentState::ACCEPTED->value);
    }

    public function testSendsEntityDataToGateway(): void
    {
        $ids = new IdsCollection();

        $client = $this->getMockHttpClient();
        $client->setResponseFactory(function ($method, $url, $options) use ($ids) {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            $shopId = $this->getContainer()->get(ShopIdProvider::class)->getShopId();
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $headers = array_values($options['headers']);

            static::assertSame(Request::METHOD_POST, $method);
            static::assertStringContainsString('/v1/entities', $url);
            static::assertContains('Shopware-Shop-Id: ' . $shopId, $headers);
            static::assertContains('Content-Type: application/json', $headers);

            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::CREATE->value, $payload['operation']);

            static::assertArrayHasKey('entities', $payload);
            static::assertCount(2, $payload['entities']);

            $firstProduct = $payload['entities'][0];
            static::assertIsArray($firstProduct);
            static::assertArrayHasKey('id', $firstProduct);
            static::assertSame($ids->get('test-product-1'), $firstProduct['id']);

            // product.categoriesRo are not in the usage-data-allow-list.json
            static::assertArrayNotHasKey('categoriesRo', $firstProduct);

            static::assertArrayHasKey('customFieldSets', $firstProduct);
            static::assertSame($firstProduct['customFieldSets'], [
                $ids->get('test-customFieldSet-1'),
            ]);

            $secondProduct = $payload['entities'][1];
            static::assertIsArray($secondProduct);
            static::assertArrayHasKey('id', $secondProduct);
            static::assertSame($ids->get('test-product-2'), $secondProduct['id']);

            // product.categoriesRo are not in the usage-data-allow-list.json
            static::assertArrayNotHasKey('categoriesRo', $secondProduct);

            static::assertArrayHasKey('customFieldSets', $secondProduct);
            static::assertSame($secondProduct['customFieldSets'], [
                $ids->get('test-customFieldSet-1'),
                $ids->get('test-customFieldSet-2'),
            ]);

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->addProductDefinition();

        $this->createTestProduct($ids, 'test-product-1');
        $this->createTestProduct($ids, 'test-product-2');

        $this->createTestCategory($ids, 'test-category-1');
        $this->createTestCategory($ids, 'test-category-2');

        $this->insertProductCategoryTree($ids->get('test-product-1'), $ids->get('test-category-1'));
        $this->insertProductCategoryTree($ids->get('test-product-1'), $ids->get('test-category-2'));

        // product 2 only has 1 entry
        $this->insertProductCategoryTree($ids->get('test-product-2'), $ids->get('test-category-2'));

        $this->createCustomFieldSet($ids->get('test-customFieldSet-1'));
        $this->createCustomFieldSet($ids->get('test-customFieldSet-2'));

        // product 1 only has 1 entry
        $this->insertProductCustomFieldSet($ids->get('test-product-1'), $ids->get('test-customFieldSet-1'));
        $this->insertProductCustomFieldSet($ids->get('test-product-2'), $ids->get('test-customFieldSet-1'));
        $this->insertProductCustomFieldSet($ids->get('test-product-2'), $ids->get('test-customFieldSet-2'));

        $dispatchEntityMessage = new DispatchEntityMessage(
            'product',
            Operation::CREATE,
            new \DateTimeImmutable(),
            [
                ['id' => $ids->get('test-product-1')],
                ['id' => $ids->get('test-product-2')],
            ]
        );

        $messageHandler = $this->getContainer()->get(DispatchEntityMessageHandler::class);
        $messageHandler($dispatchEntityMessage);
    }

    public function testSendsTranslationEntityDataToGateway(): void
    {
        $ids = new IdsCollection();

        $client = $this->getMockHttpClient();
        $client->setResponseFactory(function ($method, $url, $options) use ($ids) {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            $shopId = $this->getContainer()->get(ShopIdProvider::class)->getShopId();
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $headers = array_values($options['headers']);

            static::assertSame(Request::METHOD_POST, $method);
            static::assertStringContainsString('/v1/entities', $url);
            static::assertContains('Shopware-Shop-Id: ' . $shopId, $headers);
            static::assertContains('Content-Type: application/json', $headers);

            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::CREATE->value, $payload['operation']);

            static::assertArrayHasKey('entities', $payload);
            static::assertCount(2, $payload['entities']);

            $firstProductTranslation = $payload['entities'][0];
            static::assertIsArray($firstProductTranslation);
            static::assertArrayNotHasKey('productVersionId', $firstProductTranslation);

            static::assertArrayHasKey('productId', $firstProductTranslation);
            static::assertSame($ids->get('test-product-1'), $firstProductTranslation['productId']);

            static::assertArrayHasKey('languageId', $firstProductTranslation);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, $firstProductTranslation['languageId']);

            $secondProductTranslation = $payload['entities'][1];
            static::assertIsArray($secondProductTranslation);
            static::assertArrayNotHasKey('productVersionId', $secondProductTranslation);

            static::assertArrayHasKey('productId', $secondProductTranslation);
            static::assertSame($ids->get('test-product-2'), $secondProductTranslation['productId']);

            static::assertArrayHasKey('languageId', $secondProductTranslation);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, $secondProductTranslation['languageId']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->addProductDefinition();

        $this->createTestProduct($ids, 'test-product-1');
        $this->createTestProduct($ids, 'test-product-2');

        $dispatchEntityMessage = new DispatchEntityMessage(
            'product_translation',
            Operation::CREATE,
            new \DateTimeImmutable(),
            [
                [
                    'product_id' => $ids->get('test-product-1'),
                    'product_version_id' => Defaults::LIVE_VERSION,
                    'language_id' => Defaults::LANGUAGE_SYSTEM,
                ],
                [
                    'product_id' => $ids->get('test-product-2'),
                    'product_version_id' => Defaults::LIVE_VERSION,
                    'language_id' => Defaults::LANGUAGE_SYSTEM,
                ],
            ]
        );

        $messageHandler = $this->getContainer()->get(DispatchEntityMessageHandler::class);
        $messageHandler($dispatchEntityMessage);
    }

    public function testSendsUpdatedEntityDataToGateway(): void
    {
        $ids = new IdsCollection();

        $client = $this->getMockHttpClient();
        $client->setResponseFactory(function ($method, $url, $options) {
            $shopId = $this->getContainer()->get(ShopIdProvider::class)->getShopId();
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $headers = array_values($options['headers']);

            static::assertSame(Request::METHOD_POST, $method);
            static::assertContains('Shopware-Shop-Id: ' . $shopId, $headers);
            static::assertContains('Content-Type: application/json', $headers);

            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::UPDATE->value, $payload['operation']);

            static::assertArrayHasKey('entities', $payload);

            // no entities should be sent, because updated_at is in the future
            static::assertCount(0, $payload['entities']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->addProductDefinition();

        $product = (new ProductBuilder($ids, 'test-product'))
            ->name('Testing product')
            ->price(100)
            ->build();

        $this->getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        // update updated_at to be in the future
        $currentTime = new \DateTimeImmutable();
        $qb = $this->connection->createQueryBuilder();
        $qb->update('product')
            ->set('updated_at', ':updatedAt')
            ->setParameter('updatedAt', $currentTime->add(new DateInterval('P1D'))->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->executeQuery();

        $dispatchEntityMessage = new DispatchEntityMessage(
            'product',
            Operation::UPDATE,
            new \DateTimeImmutable(),
            [['id' => $ids->get('test-product')]]
        );

        // message handlers are inlined
        $messageHandler = $this->getContainer()->get(DispatchEntityMessageHandler::class);
        $messageHandler($dispatchEntityMessage);
    }

    public function testSendsPuidEntityDataToGateway(): void
    {
        $ids = new IdsCollection();

        $client = $this->getMockHttpClient();
        $client->setResponseFactory(function ($method, $url, $options) use ($ids) {
            $shopId = $this->getContainer()->get(ShopIdProvider::class)->getShopId();
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $headers = array_values($options['headers']);

            static::assertSame(Request::METHOD_POST, $method);
            static::assertContains('Shopware-Shop-Id: ' . $shopId, $headers);
            static::assertContains('Content-Type: application/json', $headers);

            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::CREATE->value, $payload['operation']);

            static::assertArrayHasKey('entities', $payload);

            static::assertCount(1, $payload['entities']);

            $expectedPuid = self::getPuid('recipient_fist_name', 'recipient_last_name', 'puid-test@shopware-test.com');
            $newsletterRecipient = $payload['entities'][0];

            static::assertIsArray($newsletterRecipient);
            static::assertArrayHasKey('id', $newsletterRecipient);
            static::assertSame($ids->get('newsletter-recipient-test'), $newsletterRecipient['id']);
            static::assertArrayHasKey('puid', $newsletterRecipient);
            static::assertSame($expectedPuid, $newsletterRecipient['puid']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->addNewsletterRecipientDefinition();

        $newsletterRecipient = $this->createTestNewsLetterRecipientData(
            $ids->get('newsletter-recipient-test'),
            'puid-test@shopware-test.com',
            'recipient_fist_name',
            'recipient_last_name',
        );

        $this->getContainer()->get('newsletter_recipient.repository')
            ->create([$newsletterRecipient], Context::createDefaultContext());

        $dispatchEntityMessage = new DispatchEntityMessage(
            NewsletterRecipientDefinition::ENTITY_NAME,
            Operation::CREATE,
            new \DateTimeImmutable(),
            [['id' => $ids->get('newsletter-recipient-test')]],
        );

        $messageHandler = $this->getContainer()->get(DispatchEntityMessageHandler::class);
        $messageHandler($dispatchEntityMessage);
    }

    public function testHandleDeletionOfEntities(): void
    {
        $firstEntity = $this->insertEntityDeletionEntry($this->idsCollection->get('product-entity-deletion-1'));
        $secondEntity = $this->insertEntityDeletionEntry($this->idsCollection->get('product-entity-deletion-2'));

        $client = $this->getMockHttpClient();
        $client->setResponseFactory(function ($method, $url, $options) use ($firstEntity, $secondEntity) {
            $shopId = $this->getContainer()->get(ShopIdProvider::class)->getShopId();
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $headers = array_values($options['headers']);

            static::assertSame(Request::METHOD_POST, $method);
            static::assertContains('Shopware-Shop-Id: ' . $shopId, $headers);
            static::assertContains('Content-Type: application/json', $headers);

            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::DELETE->value, $payload['operation']);

            static::assertArrayHasKey('entities', $payload);
            static::assertCount(2, $payload['entities']);
            static::assertSame([
                json_decode($firstEntity['entity_ids'], true),
                json_decode($secondEntity['entity_ids'], true),
            ], $payload['entities']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->addProductDefinition();

        $dispatchEntityMessage = new DispatchEntityMessage(
            'product',
            Operation::DELETE,
            new \DateTimeImmutable(),
            [
                ['id' => Uuid::fromBytesToHex($firstEntity['id'])],
                ['id' => Uuid::fromBytesToHex($secondEntity['id'])],
            ]
        );

        // message handlers are inlined
        $messageHandler = $this->getContainer()->get(DispatchEntityMessageHandler::class);
        $messageHandler($dispatchEntityMessage);
    }

    private function addProductDefinition(): void
    {
        $entityDefinitionService = $this->getContainer()->get(EntityDefinitionService::class);
        $entityDefinitionService->addEntityDefinition($this->getContainer()->get(ProductDefinition::class));
        $entityDefinitionService->addEntityDefinition($this->getContainer()->get(CategoryDefinition::class));
        $entityDefinitionService->addEntityDefinition($this->getContainer()->get(CustomFieldSetDefinition::class));
    }

    private function addNewsletterRecipientDefinition(): void
    {
        $entityDefinitionService = $this->getContainer()->get(EntityDefinitionService::class);
        $entityDefinitionService->addEntityDefinition($this->getContainer()->get(NewsletterRecipientDefinition::class));
    }

    /**
     * @return array{id: string, entity_name: string, entity_ids: string, deleted_at: string}
     */
    private function insertEntityDeletionEntry(string $id): array
    {
        $data = [
            'id' => Uuid::fromHexToBytes($id),
            'entity_name' => 'product_category',
            'entity_ids' => json_encode(['product_id' => Uuid::randomHex(), 'category_id' => Uuid::randomHex()], \JSON_THROW_ON_ERROR),
            'deleted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->connection->insert('usage_data_entity_deletion', $data);

        return $data;
    }

    private static function getPuid(string $name, string $lastName, string $email): string
    {
        return Hasher::hash(\sprintf('%s%s%s', strtolower($name), strtolower($lastName), strtolower($email)), 'sha512');
    }

    /**
     * @return array<string, string>
     */
    private function createTestNewsLetterRecipientData(
        string $id,
        string $email,
        string $firstName,
        string $lastName,
    ): array {
        return [
            'id' => $id,
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'status' => 'pending',
            'hash' => 'recipient_hash',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ];
    }

    private function createTestProduct(IdsCollection $idsCollection, string $productNumber): void
    {
        $product = (new ProductBuilder($idsCollection, $productNumber))
            ->name('Testing product')
            ->price(100)
            ->translation(Defaults::LANGUAGE_SYSTEM, 'title', 'my awesome product')
            ->build();

        $this->getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
    }

    private function createTestCategory(IdsCollection $idsCollection, string $categoryName): void
    {
        $this->getContainer()->get('category.repository')
            ->create([['id' => $idsCollection->get($categoryName), 'name' => $categoryName]], Context::createDefaultContext());
    }

    private function insertProductCategoryTree(string $productId, string $categoryId): void
    {
        $this->connection->insert('product_category_tree', [
            'product_id' => Uuid::fromHexToBytes($productId),
            'category_id' => Uuid::fromHexToBytes($categoryId),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ], [
            'product_id' => ParameterType::BINARY,
            'category_id' => ParameterType::BINARY,
            'product_version_id' => ParameterType::BINARY,
            'category_version_id' => ParameterType::BINARY,
        ]);
    }

    private function insertProductCustomFieldSet(string $productId, string $customFieldSetId): void
    {
        $this->connection->insert('product_custom_field_set', [
            'product_id' => Uuid::fromHexToBytes($productId),
            'custom_field_set_id' => Uuid::fromHexToBytes($customFieldSetId),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ], [
            'product_id' => ParameterType::BINARY,
            'custom_field_set_id' => ParameterType::BINARY,
            'product_version_id' => ParameterType::BINARY,
        ]);
    }

    private function createCustomFieldSet(string $id): void
    {
        $repo = $this->getContainer()->get('custom_field_set.repository');

        $attributeSet = [
            'id' => $id,
            'name' => 'test set',
            'config' => ['description' => 'test set'],
            'customFields' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => Uuid::randomHex(),
                    'type' => 'int',
                ],
                [
                    'id' => Uuid::randomHex(),
                    'name' => Uuid::randomHex(),
                    'type' => 'string',
                ],
            ],
            'relations' => [
                [
                    'entityName' => 'product',
                ],
                [
                    'entityName' => 'order',
                ],
            ],
        ];
        $repo->create([$attributeSet], Context::createDefaultContext());
    }

    private function getMockHttpClient(): MockHttpClient
    {
        $client = $this->getContainer()->get('shopware.usage_data.gateway.client');
        static::assertInstanceOf(MockHttpClient::class, $client);

        return $client;
    }
}
