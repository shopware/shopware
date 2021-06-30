<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\LiveVersionDeleteException;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group slow
 */
class ApiControllerTest extends TestCase
{
    use KernelTestBehaviour;
    use FilesystemBehaviour;
    use BasicTestDataBehaviour;
    use AdminApiTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $dropStatement = <<<EOF
DROP TABLE IF EXISTS `named`;
DROP TABLE IF EXISTS `named_optional_group`;
EOF;

        $namedOptionalGroupStatement = <<<EOF
CREATE TABLE `named_optional_group` (
    `id` binary(16) NOT NULL,
    `name` varchar(255) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY `id` (`id`)
);
EOF;

        $namedStatement = <<<EOF
CREATE TABLE `named` (
    `id` binary(16) NOT NULL,
    `name` varchar(255) NOT NULL,
    `optional_group_id` varbinary(16) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY `id` (`id`),
    CONSTRAINT `fk` FOREIGN KEY (`optional_group_id`) REFERENCES `named_optional_group` (`id`) ON DELETE SET NULL
);
EOF;
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->executeUpdate($dropStatement);
        $this->connection->executeUpdate($namedOptionalGroupStatement);
        $this->connection->executeUpdate($namedStatement);

        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();

        $this->connection->executeUpdate('DROP TABLE IF EXISTS `named`');
        $this->connection->executeUpdate('DROP TABLE IF EXISTS `named_optional_group`');

        parent::tearDown();
    }

    public function testInsert(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->request('GET', '/api/product/' . $id);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
    }

    public function testInsertAuthenticatedWithIntegration(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false],
            ],
        ];

        $this->getBrowserAuthenticatedWithIntegration()->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $this->getBrowserAuthenticatedWithIntegration()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->getBrowserAuthenticatedWithIntegration()->request('GET', '/api/product/' . $id);
        static::assertSame(Response::HTTP_OK, $this->getBrowserAuthenticatedWithIntegration()->getResponse()->getStatusCode(), $this->getBrowserAuthenticatedWithIntegration()->getResponse()->getContent());
    }

    public function testOneToManyInsert(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id];

        $this->getBrowser()->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->request('GET', '/api/country/' . $id);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $data = [
            'id' => $id,
            'name' => 'test_state',
            'shortCode' => 'test',
        ];

        $this->getBrowser()->request('POST', '/api/country/' . $id . '/states/', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/country-state/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->request('GET', '/api/country/' . $id . '/states/');
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        static::assertArrayHasKey('data', $responseData);
        static::assertCount(1, $responseData['data'], sprintf('Expected country %s has only one state', $id));

        static::assertArrayHasKey('data', $responseData);
        static::assertEquals(1, $responseData['meta']['total']);

        static::assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        static::assertSame($data['shortCode'], $responseData['data'][0]['attributes']['shortCode']);
    }

    public function testOneToManyInsertWithoutPermission(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id];
        $browser = $this->getBrowser();
        $connection = $this->getBrowser()->getContainer()->get(Connection::class);
        $user = TestUser::createNewTestUser($connection, ['country:create', 'country:read']);
        $admin = TestUser::getAdmin();

        $user->authorizeBrowser($browser);

        $browser->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'country', $id);

        $data = [
            'id' => $id,
            'name' => 'test_state',
            'shortCode' => 'test',
        ];

        $browser->request('POST', '/api/country/' . $id . '/states/', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());

        $admin->authorizeBrowser($browser);

        $this->assertEntityNotExists($browser, 'country-state', $id);
    }

    public function testTranslatedPropertiesWritableWithParentDefinitionPermissions(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id];

        $this->getBrowser()->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $browser = $this->getBrowser();
        $connection = $this->getBrowser()->getContainer()->get(Connection::class);
        $user = TestUser::createNewTestUser($connection, ['country:update', 'country:read']);

        $user->authorizeBrowser($browser);

        $data = ['name' => 'not in system language'];
        $languageId = $this->getNonSystemLanguageId();
        $browser->setServerParameter('HTTP_sw-language-id', $languageId);

        $browser->request(
            'PATCH',
            '/api/country/' . $id,
            [],
            [],
            [],
            json_encode($data)
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'country', $id);
    }

    public function testCreateAndDeleteWithPermissions(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $user = TestUser::createNewTestUser($connection, ['product:create', 'product:delete', 'tax:create']);

        $browser = $this->getBrowser();
        $user->authorizeBrowser($browser);

        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
        ];

        $browser->request('POST', '/api/product', [], [], [], json_encode($data));
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $browser->request('DELETE', '/api/product/' . $id);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
    }

    public function testTranslatedPropertiesNotWritableWithoutParentDefinitionPermissions(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id];

        $this->getBrowser()->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $browser = $this->getBrowser();
        $connection = $this->getBrowser()->getContainer()->get(Connection::class);
        $user = TestUser::createNewTestUser($connection, ['country:create', 'country:read']);

        $user->authorizeBrowser($browser);

        $data = ['name' => 'not in system language'];
        $languageId = $this->getNonSystemLanguageId();
        $browser->setServerParameter('HTTP_sw-language-id', $languageId);

        $browser->request(
            'PATCH',
            '/api/country/' . $id,
            [],
            [],
            [],
            json_encode($data)
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
    }

    public function testManyToOneInsert(): void
    {
        $id = Uuid::randomHex();
        $manufacturer = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), 'Create product failed id:' . $id);
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $manufacturer,
            'name' => 'Manufacturer - 1',
            'link' => 'https://www.shopware.com',
        ];

        $this->getBrowser()->request('POST', '/api/product/' . $id . '/manufacturer', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), 'Create manufacturer over product failed id:' . $id . "\n" . $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product-manufacturer/' . $manufacturer, $response->headers->get('Location'));

        $this->getBrowser()->request('GET', '/api/product/' . $id . '/manufacturer');
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), 'Read manufacturer of product failed id: ' . $id . \PHP_EOL . $this->getBrowser()->getResponse()->getContent());

        static::assertArrayHasKey('data', $responseData, $this->getBrowser()->getResponse()->getContent());
        static::assertArrayHasKey(0, $responseData['data'], $this->getBrowser()->getResponse()->getContent());
        static::assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        static::assertSame($data['link'], $responseData['data'][0]['attributes']['link']);
        static::assertSame($data['id'], $responseData['data'][0]['id']);
    }

    public function testManyToOneInsertWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $manufacturer = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'stock' => 12,
            'productNumber' => '1',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
        ];

        $browser = $this->getBrowser();
        $connection = $this->getBrowser()->getContainer()->get(Connection::class);
        $user = TestUser::createNewTestUser($connection, ['product:create', 'product:read']);
        $admin = TestUser::getAdmin();

        $browser->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), 'Create product failed id:' . $id);
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $user->authorizeBrowser($browser);

        $data = [
            'id' => $manufacturer,
            'name' => 'Manufacturer - 1',
            'link' => 'https://www.shopware.com',
        ];

        $browser->request('POST', '/api/product/' . $id . '/manufacturer', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());

        $admin->authorizeBrowser($browser);

        $this->assertEntityNotExists($browser, 'product-manufacturer', $manufacturer);

        $browser->request('GET', '/api/product/' . $id . '/manufacturer');
        $responseData = json_decode($browser->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), 'Read manufacturer of product failed id: ' . $id . \PHP_EOL . $browser->getResponse()->getContent());

        static::assertArrayHasKey('data', $responseData, $browser->getResponse()->getContent());
        static::assertArrayHasKey(0, $responseData['data'], $browser->getResponse()->getContent());
        static::assertSame('test', $responseData['data'][0]['attributes']['name']);
    }

    public function testManyToManyInsert(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $id,
            'name' => 'Category - 1',
        ];

        $this->getBrowser()->request('POST', '/api/product/' . $id . '/categories/', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/category/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->request('GET', '/api/product/' . $id . '/categories/');
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        static::assertArrayHasKey('data', $responseData);
        static::assertCount(1, $responseData['data']);
        static::assertArrayHasKey('attributes', $responseData['data'][0]);
        static::assertArrayHasKey('name', $responseData['data'][0]['attributes'], print_r($responseData, true));
        static::assertSame($data['name'], $responseData['data'][0]['attributes']['name']);
        static::assertSame($data['id'], $responseData['data'][0]['id']);
    }

    public function testManyToManyInsertWithoutPermission(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'stock' => 12,
            'productNumber' => '1',
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
        ];

        $browser = $this->getBrowser();

        $connection = $this->getBrowser()->getContainer()->get(Connection::class);
        $user = TestUser::createNewTestUser(
            $connection,
            ['product:create', 'product:read', 'tax:create', 'tax:read', 'product_manufacturer:create', 'product_manufacturer:read', 'product_price:create', 'product_price:read', 'version_commit_data:create', ':version_commitcreate']
        );
        $admin = TestUser::getAdmin();

        $user->authorizeBrowser($browser);

        $browser->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $id,
            'name' => 'Category - 1',
        ];

        $browser->request('POST', '/api/product/' . $id . '/categories/', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());

        $admin->authorizeBrowser($browser);

        $this->assertEntityNotExists($browser, 'category', $id);

        $browser->request('GET', '/api/product/' . $id . '/categories/');
        $responseData = json_decode($browser->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        static::assertArrayHasKey('data', $responseData);
        static::assertCount(0, $responseData['data']);
    }

    public function testDelete(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'product', $id);

        $this->getBrowser()->request('DELETE', '/api/product/' . $id);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $this->assertEntityNotExists($this->getBrowser(), 'product', $id);
    }

    public function testDeleteVersion(): void
    {
        $id = Uuid::randomHex();
        $browser = $this->getBrowser();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $browser->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'product', $id);

        $browser->request('POST', '/api/_action/version/product/' . $id);
        $response = json_decode($browser->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertArrayHasKey('versionId', $response);
        static::assertArrayHasKey('versionName', $response);
        static::assertArrayHasKey('id', $response);
        static::assertArrayHasKey('entity', $response);
        static::assertTrue(Uuid::isValid($response['versionId']));
        $versionId = $response['versionId'];

        $browser->request('POST', '/api/_action/version/' . $response['versionId'] . '/product/' . $id);
        $response = json_decode($browser->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertEmpty($response);

        $this->assertEntityExists($browser, 'product', $id);

        /** @var EntityRepositoryInterface $productRepo */
        $productRepo = $this->getContainer()->get(ProductDefinition::ENTITY_NAME . '.repository');
        $criteria = new Criteria([$id]);
        $criteria->addFilter(
            new EqualsFilter('versionId', $versionId)
        );

        static::assertCount(0, $productRepo->search($criteria, Context::createDefaultContext()));
    }

    public function testDeleteVersionWithLiveVersion(): void
    {
        $id = Uuid::randomHex();
        $browser = $this->getBrowser();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $browser->request('POST', '/api/product', [], [], [], json_encode($data));

        $browser->request('POST', '/api/_action/version/' . Defaults::LIVE_VERSION . '/product/' . $id);

        $repo = $this->getContainer()->get(ProductDefinition::ENTITY_NAME . '.repository');
        $criteria = new Criteria([$id]);
        $criteria->addFilter(new EqualsFilter('versionId', Defaults::LIVE_VERSION));

        static::assertNotNull($repo->search($criteria, Context::createDefaultContext())->getEntities()->first());

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        static::assertSame((new LiveVersionDeleteException())->getErrorCode(), $content['errors'][0]['code']);
    }

    public function testDeleteWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test tax',
            'taxRate' => 15,
        ];

        $browser = $this->getBrowser();

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['tax:read', 'tax:create']
        )->authorizeBrowser($browser);

        $browser->request('POST', '/api/tax', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $browser->request('DELETE', '/api/tax/' . $id, ['name' => 'foo']);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());

        $this->assertEntityExists($browser, 'tax', $id);
    }

    public function testDeleteOneToMany(): void
    {
        $id = Uuid::randomHex();
        $stateId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'states' => [
                ['id' => $stateId, 'shortCode' => 'test', 'name' => 'test'],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'country', $id);
        $this->assertEntityExists($this->getBrowser(), 'country-state', $stateId);

        $this->getBrowser()->request('DELETE', '/api/country/' . $id . '/states/' . $stateId, [], [], [], json_encode($data));
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $this->assertEntityExists($this->getBrowser(), 'country', $id);
        $this->assertEntityNotExists($this->getBrowser(), 'country-state', $stateId);
    }

    public function testDeleteOneToManyWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $stateId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'states' => [
                ['id' => $stateId, 'shortCode' => 'test', 'name' => 'test'],
            ],
        ];

        $browser = $this->getBrowser();

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['country_state:create', 'country_state:read', 'country:create', 'country:read']
        )->authorizeBrowser($browser);

        $browser->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'country', $id);
        $this->assertEntityExists($browser, 'country-state', $stateId);

        $browser->request('DELETE', '/api/country/' . $id . '/states/' . $stateId, [], [], [], json_encode($data));
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->assertEntityExists($browser, 'country', $id);
        $this->assertEntityExists($browser, 'country-state', $stateId);
    }

    public function testDeleteManyToOne(): void
    {
        $id = Uuid::randomHex();
        $groupId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'Test product',
            'optionalGroup' => [
                'id' => $groupId,
                'name' => 'Gramm',
            ],
        ];
        $this->getBrowser()->request('POST', '/api/named', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/named/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'named', $id);
        $this->assertEntityExists($this->getBrowser(), 'named-optional-group', $groupId);

        $this->getBrowser()->request('DELETE', '/api/named/' . $id . '/optional-group/' . $groupId);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $this->assertEntityExists($this->getBrowser(), 'named', $id);
        $this->assertEntityNotExists($this->getBrowser(), 'named-optional-group', $groupId);
    }

    public function testDeleteManyToMany(): void
    {
        $id = Uuid::randomHex();
        $category = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'categories' => [
                ['id' => $category, 'name' => 'Test'],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'product', $id);
        $this->assertEntityExists($this->getBrowser(), 'category', $category);

        $this->getBrowser()->request('DELETE', '/api/product/' . $id . '/categories/' . $category);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $a = $this->getContainer()
            ->get(Connection::class)
            ->executeQuery(
                'SELECT * FROM product_category WHERE product_id = :pid AND category_id = :cid',
                ['pid' => Uuid::fromHexToBytes($id), 'cid' => Uuid::fromHexToBytes($category)]
            )->fetchAll();
        static::assertEmpty($a);

        $this->assertEntityExists($this->getBrowser(), 'product', $id);
        $this->assertEntityExists($this->getBrowser(), 'category', $category);
    }

    public function testDeleteManyToManyWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $category = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'stock' => 12,
            'productNumber' => '1',
            'manufacturer' => ['name' => 'test'],
            'categories' => [
                ['id' => $category, 'name' => 'Test'],
            ],
        ];

        $browser = $this->getBrowser();
        $browser->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'product', $id);
        $this->assertEntityExists($browser, 'category', $category);

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['product:read', 'category:read']
        )->authorizeBrowser($browser);

        $browser->request('DELETE', '/api/product/' . $id . '/categories/' . $category);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $a = $this->getContainer()->get(Connection::class)->executeQuery('SELECT * FROM product_category WHERE product_id = :pid AND category_id = :cid', ['pid' => Uuid::fromHexToBytes($id), 'cid' => Uuid::fromHexToBytes($category)])->fetchAll();
        static::assertNotEmpty($a);

        $this->assertEntityExists($browser, 'product', $id);
        $this->assertEntityExists($browser, 'category', $category);
    }

    public function testResponseDataTypeOnWrite(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id, 'taxRate' => 50];

        // create without response
        $this->getBrowser()->request('POST', '/api/tax', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/tax/' . $id, $response->headers->get('Location'));

        // update without response
        $this->getBrowser()->request('PATCH', '/api/tax/' . $id, ['name' => 'foo']);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/tax/' . $id, $response->headers->get('Location'));

        // with response
        $this->getBrowser()->request('PATCH', '/api/tax/' . $id . '?_response=1', ['name' => 'foo']);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertNull($response->headers->get('Location'));
    }

    public function testSearchTerm(): void
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => 'SW-API-14999',
            'stock' => 1,
            'name' => 'asdf',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($product));

        $data = [
            'page' => 1,
            'limit' => 5,
            'sort' => [
                [
                    'field' => 'productNumber',
                    'order' => 'desc',
                ],
            ],
            'term' => 'SW-API-14999',
        ];

        $this->getBrowser()->request('POST', '/api/search/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertArrayHasKey('meta', $content, print_r($content, true));
        static::assertEquals(1, $content['meta']['total']);
        static::assertEquals($id, $content['data'][0]['id']);
    }

    public function testSearchNonTokenizeTerm(): void
    {
        // Create two customers with different email but same suffix example.com
        $this->createCustomer();
        $ids = $this->createCustomer();

        $data = [
            'page' => 1,
            'limit' => 5,
            'sort' => [
                [
                    'field' => 'customerNumber',
                    'order' => 'desc',
                ],
            ],
            'term' => $ids->get('email') . '@example.com',
        ];

        $this->getBrowser()->request('POST', '/api/search/customer', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertArrayHasKey('meta', $content, print_r($content, true));
        static::assertEquals(1, $content['meta']['total']);
        static::assertEquals($ids->get('customer'), $content['data'][0]['id']);

        $data['term'] = 'example.com';

        $this->getBrowser()->request('POST', '/api/search/customer', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertArrayHasKey('meta', $content, print_r($content, true));
        static::assertEquals(2, $content['meta']['total']);
    }

    public function testSearch(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Cotton Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'page' => 1,
            'limit' => 5,
            'total-count-mode' => Criteria::TOTAL_COUNT_MODE_EXACT,
            'sort' => [
                [
                    'field' => 'product.stock',
                    'order' => 'desc',
                ],
                [
                    'field' => 'product.name',
                    'order' => 'desc',
                ],
            ],
            'filter' => [
                [
                    'type' => 'multi',
                    'queries' => [
                        [
                            'type' => 'range',
                            'field' => 'product.price',
                            'parameters' => [
                                'gt' => 49,
                                'lte' => 50,
                            ],
                        ],
                        [
                            'type' => 'equals',
                            'field' => 'product.manufacturer.name',
                            'value' => 'Shopware AG',
                        ],
                        [
                            'type' => 'equalsAny',
                            'field' => 'product.id',
                            'value' => $id,
                        ],
                    ],
                ],
            ],
            'query' => [
                [
                    'type' => 'score',
                    'query' => [
                        'type' => 'contains',
                        'field' => 'product.name',
                        'value' => 'Cotton',
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/search/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertArrayHasKey('meta', $content, print_r($content, true));
        static::assertEquals(1, $content['meta']['total']);
        static::assertEquals($id, $content['data'][0]['id']);

        $this->getBrowser()->request('DELETE', '/api/product/' . $id);
        static::assertEquals(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testSearchWithoutPermission(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'Cotton Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'stock' => 12,
            'productNumber' => '1',
        ];

        $browser = $this->getBrowser();

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['product:create', 'tax:create', 'product_manufacturer:create', 'price:create', 'version_commit_data:create', 'version_commit:create']
        )->authorizeBrowser($browser);

        $browser->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'page' => 1,
            'limit' => 5,
        ];

        $browser->request('POST', '/api/search/product', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
    }

    /**
     * Tests the API search endpoint. Asserts that an entity can be both part of the result data as well as the
     * associations when the entity is fetched as a top level entity result and through circular associations.
     */
    public function testEntityIsPresentInTopLevelEntityResultWhenAlsoPartOfAssociations(): void
    {
        // In this test case both products are created with the same base data (i.e. they are part of the same sales
        // channel).
        $productBase = [
            'name' => 'Some product',
            'stock' => 1,
            'tax' => [
                'name' => 'test',
                'taxRate' => 10,
            ],
            'manufacturer' => [
                'name' => 'Shopware AG',
            ],
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 50,
                    'net' => 25,
                    'linked' => false,
                ],
            ],
            'visibilities' => [
                [
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $product1 = array_merge($productBase, [
            'id' => Uuid::randomHex(),
            'productNumber' => 'product-1',
        ]);
        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($product1));

        $product2 = array_merge($productBase, [
            'id' => Uuid::randomHex(),
            'productNumber' => 'product-2',
        ]);
        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($product2));

        // Add associations so that the products are both part of the top level entity result as well as the
        // associations through the circular association chain.
        $data = [
            'page' => 1,
            'limit' => 25,
            'associations' => [
                'visibilities' => [
                    'associations' => [
                        'salesChannel' => [
                            'associations' => [
                                'productVisibilities' => [
                                    'associations' => [
                                        'product' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/search/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        $searchResult = json_decode($response->getContent(), true);
        static::assertCount(2, $searchResult['data']);
    }

    public function testNestedSearchOnOneToMany(): void
    {
        $id = Uuid::randomHex();

        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $ruleA,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $ruleB,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/product/' . $id . '/prices';
        $this->getBrowser()->request('GET', $path);
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(2, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'product_price.ruleId',
                    'value' => $ruleA,
                ],
            ],
        ];

        $path = '/api/search/product/' . $id . '/prices';
        $this->getBrowser()->request('POST', $path, $filter);
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(1, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);
    }

    public function testNestedSearchOnOneToManyWithoutPermissionOnParent(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'states' => [
                [
                    'name' => 'test_state',
                    'shortCode' => 'test',
                ],
                [
                    'name' => 'test_state_2',
                    'shortCode' => 'test 2',
                ],
            ],
        ];

        $browser = $this->getBrowser();
        $browser->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['country_state:list']
        )->authorizeBrowser($browser);

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'country_state.name',
                    'value' => 'test_state',
                ],
            ],
        ];

        $path = '/api/search/country/' . $id . '/states';
        $browser->request('POST', $path, $filter);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
    }

    public function testNestedSearchOnOneToManyWithoutPermissionOnChild(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'states' => [
                [
                    'name' => 'test_state',
                    'shortCode' => 'test',
                ],
                [
                    'name' => 'test_state_2',
                    'shortCode' => 'test 2',
                ],
            ],
        ];

        $browser = $this->getBrowser();
        $browser->request('POST', '/api/country', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['country:list']
        )->authorizeBrowser($browser);

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'country_state.name',
                    'value' => 'test_state',
                ],
            ],
        ];

        $path = '/api/search/country/' . $id . '/states';
        $browser->request('POST', $path, $filter);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
    }

    public function testNestedSearchOnOneToManyWithAggregation(): void
    {
        $id = Uuid::randomHex();

        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $ruleA,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $ruleB,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/product/' . $id . '/prices';
        $this->getBrowser()->request('GET', $path);
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(2, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);

        $filter = [
            'aggregations' => [
                [
                    'name' => 'price_stats',
                    'type' => 'stats',
                    'field' => 'product_price.price',
                ],
            ],
        ];

        $path = '/api/search/product/' . $id . '/prices';
        $this->getBrowser()->request('POST', $path, $filter);
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertArrayHasKey('aggregations', $responseData);
        static::assertArrayHasKey('price_stats', $responseData['aggregations']);
    }

    public function testSearchOnManyToMany(): void
    {
        $id = Uuid::randomHex();
        $categoryA = Uuid::randomHex();
        $categoryB = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryA, 'name' => 'A'],
                ['id' => $categoryB, 'name' => 'B'],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/product/' . $id . '/categories';
        $this->getBrowser()->request('GET', $path);
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(2, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'category.name',
                    'value' => 'A',
                ],
            ],
        ];

        $path = '/api/search/product/' . $id . '/categories';
        $this->getBrowser()->request('POST', $path, $filter);
        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(1, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);
    }

    public function testNestedSearchOnManyToManyWithoutPermissionOnParent(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 12,
            'productNumber' => '1',
            'categories' => [
                ['name' => 'category 1'],
                ['name' => 'category 2'],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'category.name',
                    'value' => 'category 1',
                ],
            ],
        ];

        $path = '/api/search/product/' . $id . '/categories';
        $browser = $this->getBrowser();

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['category:list']
        )->authorizeBrowser($browser);

        $browser->request('POST', $path, $filter);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
    }

    public function testNestedSearchOnManyToManyWithoutPermissionOnChild(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 12,
            'productNumber' => '1',
            'categories' => [
                ['name' => 'category 1'],
                ['name' => 'category 2'],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'category.name',
                    'value' => 'category 1',
                ],
            ],
        ];

        $path = '/api/search/product/' . $id . '/categories';
        $browser = $this->getBrowser();

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['product:list']
        )->authorizeBrowser($browser);

        $browser->request('POST', $path, $filter);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
    }

    public function testSimpleFilter(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 8300, 'net' => 8300, 'linked' => false]],
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'filter' => [
                'product.id' => $id,
                'product.price' => 8300,
                'product.name' => 'Wool Shirt',
            ],
        ];

        $this->getBrowser()->request('GET', '/api/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertEquals(1, $content['meta']['total']);
        static::assertEquals($id, $content['data'][0]['id']);
    }

    public function testAggregation(): void
    {
        $manufacturerName = Uuid::randomHex();

        $productA = Uuid::randomHex();
        $data = [
            'id' => $productA,
            'productNumber' => Uuid::randomHex(),
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => $manufacturerName],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 8300, 'net' => 8300, 'linked' => false]],
            'stock' => 50,
        ];
        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        static::assertEquals(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        $productB = Uuid::randomHex();
        $data = [
            'id' => $productB,
            'productNumber' => Uuid::randomHex(),
            'name' => 'Wool Shirt 2',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => $manufacturerName],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 8300, 'net' => 8300, 'linked' => false]],
            'stock' => 100,
        ];
        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        static::assertEquals(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        $data = [
            'aggregations' => [
                ['name' => 'product_count', 'type' => 'count', 'field' => 'product.id'],
                ['name' => 'product_stats', 'type' => 'stats', 'field' => 'product.stock'],
            ],
            'filter' => [
                [
                    'type' => 'multi',
                    'queries' => [
                        [
                            'type' => 'equals',
                            'field' => 'product.manufacturer.name',
                            'value' => $manufacturerName,
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->setServerParameter('HTTP_ACCEPT', 'application/json');
        $this->getBrowser()->request('POST', '/api/search/product', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();

        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), print_r($response->getContent(), true));
        static::assertNotEmpty($content);

        static::assertArrayHasKey('aggregations', $content);
        $aggregations = $content['aggregations'];

        static::assertArrayHasKey('product_count', $aggregations, print_r($aggregations, true));
        $productCount = $aggregations['product_count'];
        static::assertEquals(2, $productCount['count']);

        static::assertArrayHasKey('product_stats', $aggregations);
        $productStats = $aggregations['product_stats'];
        static::assertEquals(75, $productStats['avg']);
        static::assertEquals(150, $productStats['sum']);
        static::assertEquals(50, $productStats['min']);
        static::assertEquals(100, $productStats['max']);
    }

    public function testParentChildLocation(): void
    {
        $childId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $data = [
            'id' => $childId,
            'name' => 'Child Language',
            'localeId' => $this->getLocaleIdOfSystemLanguage(),
            'parent' => [
                'id' => $parentId,
                'name' => 'Parent Language',
                'locale' => [
                    'code' => 'x-tst_' . Uuid::randomHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'translationCode' => [
                    'code' => 'x-tst_' . Uuid::randomHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/language', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/language/' . $childId, $response->headers->get('Location'));
    }

    public function testJsonApiResponseSingle(): void
    {
        $id = Uuid::randomHex();
        $insertData = ['id' => $id, 'name' => 'test'];

        $this->getBrowser()->request('POST', '/api/category', [], [], [], json_encode($insertData));
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        static::assertNotEmpty($response->headers->get('Location'));

        $this->getBrowser()->request('GET', $response->headers->get('Location'));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $respData = json_decode($response->getContent(), true);

        static::assertArrayHasKey('data', $respData);
        static::assertArrayHasKey('links', $respData);
        static::assertArrayHasKey('included', $respData);

        $catData = $respData['data'];
        static::assertArrayHasKey('type', $catData);
        static::assertArrayHasKey('id', $catData);
        static::assertArrayHasKey('attributes', $catData);
        static::assertArrayHasKey('links', $catData);
        static::assertArrayHasKey('relationships', $catData);
        static::assertArrayHasKey('translations', $catData['relationships']);
        static::assertArrayHasKey('meta', $catData);
        static::assertArrayHasKey('translated', $catData['attributes']);
        static::assertArrayHasKey('name', $catData['attributes']['translated']);

        static::assertEquals($id, $catData['id']);
        static::assertEquals('category', $catData['type']);
        static::assertEquals($insertData['name'], $catData['attributes']['name']);
        static::assertEquals($insertData['name'], $catData['attributes']['translated']['name']);
    }

    public function testJsonApiResponseMulti(): void
    {
        $insertData = [
            ['name' => 'test'],
            ['name' => 'test_2'],
        ];

        $this->getBrowser()->request('POST', '/api/category', [], [], [], json_encode($insertData[0]));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()->request('POST', '/api/category', [], [], [], json_encode($insertData[1]));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()->request('GET', '/api/category?sort=name');
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $respData = json_decode($response->getContent(), true);
        static::assertArrayHasKey('data', $respData);
        static::assertArrayHasKey('links', $respData);
        static::assertArrayHasKey('included', $respData);
        static::assertCount(3, $respData['data']);

        $data = $respData['data'];
        static::assertEquals('category', $data[0]['type']);
        static::assertEquals('Home', $data[0]['attributes']['name']);
        static::assertEquals('Home', $data[0]['attributes']['translated']['name']);

        static::assertEquals('category', $data[1]['type']);
        static::assertEquals($insertData[0]['name'], $data[1]['attributes']['name']);
        static::assertEquals($insertData[0]['name'], $data[1]['attributes']['translated']['name']);

        static::assertEquals('category', $data[2]['type']);
        static::assertEquals($insertData[1]['name'], $data[2]['attributes']['name']);
        static::assertEquals($insertData[1]['name'], $data[2]['attributes']['translated']['name']);
    }

    public function testCreateNewVersion(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => 'test category'];

        $this->getBrowser()->request('POST', '/api/category', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));

        $this->getBrowser()->request(
            'POST',
            sprintf('/api/_action/version/category/%s', $id)
        );
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertTrue(Uuid::isValid($content['versionId']));
        static::assertNull($content['versionName']);
        static::assertEquals($id, $content['id']);
        static::assertEquals('category', $content['entity']);
    }

    public function testCloneEntity(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test tax clone',
            'taxRate' => 15,
        ];

        $this->getBrowser()->request('POST', '/api/tax', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()->request('GET', '/api/tax/' . $id);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $tax = json_decode($response->getContent(), true);
        static::assertArrayHasKey('data', $tax);
        static::assertEquals($id, $tax['data']['id']);

        $this->getBrowser()->request('POST', '/api/_action/clone/tax/' . $id, [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        static::assertArrayHasKey('id', $data);
        static::assertNotEquals($id, $data['id']);

        $newId = $data['id'];
        $this->getBrowser()->request('GET', '/api/tax/' . $newId);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(15, $data['data']['attributes']['taxRate']);
    }

    public function testWriteExtensionWithExtensionKey(): void
    {
        $field = (new OneToManyAssociationField('testSeoUrls', SeoUrlDefinition::class, 'sales_channel_id'))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(SalesChannelDefinition::class)->getFields()->addNewField($field);

        $salesChannelId = Uuid::randomHex();
        $this->createSalesChannel($salesChannelId);

        $data = [
            'extensions' => [
                'testSeoUrls' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'foreignKey' => $salesChannelId,
                        'routeName' => 'test',
                        'pathInfo' => 'test',
                        'seoPathInfo' => 'test',
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('PATCH', '/api/sales-channel/' . $salesChannelId, [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'id',
                    'value' => $salesChannelId,
                ],
            ],
            'associations' => [
                'testSeoUrls' => [],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/search/sales-channel', [], [], [], json_encode($filter));
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);
        $data = $result['data'];

        static::assertCount(1, $data);
        static::assertArrayHasKey('extensions', $data[0]['relationships']);

        $included = $result['included'];
        static::assertCount(2, $included);

        // sort the included entities alphabetically by type
        usort($included, function ($a, $b) {
            return $a['type'] <=> $b['type'];
        });

        $extension = $included[0];
        static::assertEquals('extension', $extension['type']);
        static::assertArrayHasKey('testSeoUrls', $extension['relationships']);

        $seoUrl = $included[1];
        static::assertEquals('seo_url', $seoUrl['type']);
        static::assertEquals('test', $seoUrl['attributes']['routeName']);

        $this->getBrowser()->request('GET', '/api/sales-channel/' . $salesChannelId . '/extensions/seo-urls');
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);
        $data = $result['data'];

        static::assertCount(1, $data);

        $seoUrl = $data[0];
        static::assertEquals('seo_url', $seoUrl['type']);
        static::assertEquals('test', $seoUrl['attributes']['routeName']);
    }

    public function testCanWriteExtensionWithoutExtensionKey(): void
    {
        $field = (new OneToManyAssociationField('testSeoUrls', SeoUrlDefinition::class, 'sales_channel_id'))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(SalesChannelDefinition::class)->getFields()->addNewField($field);

        $salesChannelId = Uuid::randomHex();
        $this->createSalesChannel($salesChannelId);

        $data = [
            'testSeoUrls' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'foreignKey' => $salesChannelId,
                    'routeName' => 'test',
                    'pathInfo' => 'test',
                    'seoPathInfo' => 'test',
                ],
            ],
        ];

        $this->getBrowser()->request('PATCH', '/api/sales-channel/' . $salesChannelId, [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'id',
                    'value' => $salesChannelId,
                ],
            ],
            'associations' => [
                'testSeoUrls' => [],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/search/sales-channel', [], [], [], json_encode($filter));
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);
        $data = $result['data'];

        static::assertCount(1, $data);
        static::assertArrayHasKey('extensions', $data[0]['relationships']);

        $included = $result['included'];
        static::assertCount(2, $included);

        // sort the included entities alphabetically by type
        usort($included, function ($a, $b) {
            return $a['type'] <=> $b['type'];
        });

        $extension = $included[0];
        static::assertEquals('extension', $extension['type']);
        static::assertArrayHasKey('testSeoUrls', $extension['relationships']);

        $seoUrls = $included[1];
        static::assertEquals('seo_url', $seoUrls['type']);
        static::assertEquals('test', $seoUrls['attributes']['routeName']);

        $this->getBrowser()->request('GET', '/api/sales-channel/' . $salesChannelId . '/extensions/seo-urls');
        $response = $this->getBrowser()->getResponse();
        $result = json_decode($response->getContent(), true);
        $data = $result['data'];

        static::assertCount(1, $data);

        $seoUrl = $data[0];
        static::assertEquals('seo_url', $seoUrl['type']);
        static::assertEquals('test', $seoUrl['attributes']['routeName']);
    }

    public function testCloneEntityWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test tax clone',
            'taxRate' => 15,
        ];

        $browser = $this->getBrowser();
        $browser->request('POST', '/api/tax', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $connection = $browser->getContainer()->get(Connection::class);
        TestUser::createNewTestUser(
            $connection,
            ['tax:read']
        )->authorizeBrowser($browser);

        $browser->request('GET', '/api/tax/' . $id);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $tax = json_decode($response->getContent(), true);
        static::assertArrayHasKey('data', $tax);
        static::assertEquals($id, $tax['data']['id']);

        $browser->request('POST', '/api/_action/clone/tax/' . $id, [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
    }

    public function testUpdateWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test tax',
            'taxRate' => 15,
        ];
        $browser = $this->getBrowser();
        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['tax:read', 'tax:create']
        )->authorizeBrowser($browser);

        $browser->request('POST', '/api/tax', [], [], [], json_encode($data));
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $browser->request('PATCH', '/api/tax/' . $id, ['name' => 'foo']);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/api/tax/' . $id);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $tax = json_decode($response->getContent(), true);
        static::assertArrayHasKey('data', $tax);
        static::assertEquals('test tax', $tax['data']['attributes']['name']);
    }

    public function testAggregationWorksForAdminStartPage(): void
    {
        $data = [
            'page' => 1,
            'limit' => 10,
            'filter' => [
                [
                    'type' => 'range',
                    'field' => 'orderDate',
                    'parameters' => [
                        'gte' => '2020-05-16',
                    ],
                ],
            ],
            'aggregations' => [
                [
                    'type' => 'histogram',
                    'name' => 'order_count_month',
                    'field' => 'orderDateTime',
                    'interval' => 'day',
                    'format' => null,
                    'aggregation' => [
                        'type' => 'sum',
                        'name' => 'totalAmount',
                        'field' => 'amountTotal',
                    ],
                ],
            ],
            'total-count-mode' => 1,
        ];

        $this->getBrowser()->request('POST', '/api/search/order', [], [], [], json_encode($data));
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertArrayHasKey('aggregations', $response);
        static::assertArrayHasKey('order_count_month', $response['aggregations']);
    }

    public function testGetBillingAddress(): void
    {
        $ids = $this->createCustomer();

        $this->getBrowser()->request('POST', '/api/search/customer/' . $ids->get('customer') . '/default-billing-address');
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $response);
        static::assertCount(1, $response['data']);
        static::assertEquals($ids->get('address'), $response['data'][0]['id']);
    }

    public function testAccessDeniedAfterChangingUserPassword(): void
    {
        $browser = $this->getBrowser();

        $connection = $browser->getContainer()->get(Connection::class);
        $admin = TestUser::createNewTestUser($connection, ['product:read']);

        $admin->authorizeBrowser($browser);

        $browser->request('POST', '/api/search/product', []);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $userRepository = $this->getContainer()->get('user.repository');

        // Change user password
        $userRepository->update([[
            'id' => $admin->getUserId(),
            'password' => Uuid::randomHex(),
        ]], Context::createDefaultContext());

        $browser->request('POST', '/api/search/product', []);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), $response->getContent());
        $jsonResponse = json_decode($response->getContent(), true);
        static::assertEquals('Access token is expired', $jsonResponse['errors'][0]['detail']);
    }

    private function createSalesChannel(string $id): void
    {
        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ];

        $this->getContainer()->get('sales_channel.repository')->create([$data], Context::createDefaultContext());
    }

    private function getNonSystemLanguageId(): string
    {
        /** @var EntityRepositoryInterface $languageRepository */
        $languageRepository = $this->getContainer()->get('language.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM),
            ]
        ));
        $criteria->setLimit(1);

        return $languageRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    private function createCustomer(): IdsCollection
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('customer'),
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => $ids->get('email') . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $ids->get('address'),
            'defaultShippingAddressId' => $ids->get('address'),
            'addresses' => [
                [
                    'id' => $ids->get('address'),
                    'customerId' => $ids->get('customer'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];
        $this->getContainer()->get('customer.repository')
            ->create([$data], $ids->getContext());

        return $ids;
    }
}
