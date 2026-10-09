<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ApiControllerVersionTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testCreateNewVersion(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => 'test category'];

        $this->getBrowser()->jsonRequest('POST', '/api/category', $data);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame('http://localhost/api/category/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->jsonRequest(
            'POST',
            \sprintf('/api/_action/version/category/%s', $id)
        );
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertTrue(Uuid::isValid($content['versionId']));
        static::assertNull($content['versionName']);
        static::assertSame($id, $content['id']);
        static::assertSame('category', $content['entity']);
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

        $browser->jsonRequest('POST', '/api/product', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotNull($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'product', $id);

        $browser->jsonRequest('POST', '/api/_action/version/product/' . $id);
        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertIsArray($response);
        static::assertArrayHasKey('versionId', $response);
        static::assertArrayHasKey('versionName', $response);
        static::assertArrayHasKey('id', $response);
        static::assertArrayHasKey('entity', $response);
        static::assertTrue(Uuid::isValid($response['versionId']));
        $versionId = $response['versionId'];

        $browser->jsonRequest('POST', '/api/_action/version/' . $response['versionId'] . '/product/' . $id);
        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame([], $response);

        $this->assertEntityExists($browser, 'product', $id);

        $actions = static::getContainer()->get(Connection::class)->fetchFirstColumn(
            'SELECT commit_data.action
             FROM version_commit_data AS commit_data
             INNER JOIN version_commit ON version_commit.id = commit_data.version_commit_id
             WHERE version_commit.version_id = :version',
            ['version' => Uuid::fromHexToBytes($versionId)]
        );

        static::assertSame([], $actions, 'a discarded version must not leave a change set behind that merge() could replay');

        $productRepo = static::getContainer()->get(ProductDefinition::ENTITY_NAME . '.repository');
        static::assertInstanceOf(EntityRepository::class, $productRepo);

        $criteria = new Criteria([$id]);
        $criteria->addFilter(
            new EqualsFilter('versionId', $versionId)
        );

        static::assertCount(0, $productRepo->search($criteria, Context::createDefaultContext())->getEntities());
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

        $browser->jsonRequest('POST', '/api/product', $data);

        $browser->jsonRequest('POST', '/api/_action/version/' . Defaults::LIVE_VERSION . '/product/' . $id);

        $repo = static::getContainer()->get(ProductDefinition::ENTITY_NAME . '.repository');
        $criteria = new Criteria([$id]);
        $criteria->addFilter(new EqualsFilter('versionId', Defaults::LIVE_VERSION));

        static::assertNotNull($repo->search($criteria, Context::createDefaultContext())->getEntities()->first());

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(ApiException::deleteLiveVersion()->getErrorCode(), $content['errors'][0]['code']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedDeleteVersionPathProvider(): iterable
    {
        $id = Uuid::randomHex();

        yield 'malformed version id' => [\sprintf('/api/_action/version/not-a-uuid/product/%s', $id)];
        yield 'malformed entity id' => [\sprintf('/api/_action/version/%s/product/not-a-uuid', $id)];
    }

    #[DataProvider('malformedDeleteVersionPathProvider')]
    public function testDeleteVersionWithAMalformedIdIsNotRouted(string $path): void
    {
        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', $path);

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public function testMergeCannotResurrectADiscardedVersion(): void
    {
        $id = Uuid::randomHex();
        $browser = $this->getBrowser();

        $browser->jsonRequest('POST', '/api/product', [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'live name',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ]);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $browser->jsonRequest('POST', '/api/_action/version/product/' . $id);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $versionId = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['versionId'];
        static::assertIsString($versionId);

        $browser->jsonRequest('PATCH', '/api/product/' . $id, ['name' => 'draft name'], ['HTTP_SW_VERSION_ID' => $versionId]);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $browser->jsonRequest('POST', '/api/_action/version/' . $versionId . '/product/' . $id);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // The discard deleted the version row; recreating it is the only way to let merge() run at all.
        static::getContainer()->get('version.repository')->create([['id' => $versionId]], Context::createDefaultContext());

        $browser->jsonRequest('POST', '/api/_action/version/merge/product/' . $versionId);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(DataAbstractionLayerException::VERSION_NO_COMMITS_FOUND, $content['errors'][0]['code']);

        $connection = static::getContainer()->get(Connection::class);
        $live = ['id' => Uuid::fromHexToBytes($id), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];

        static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM product WHERE id = :id AND version_id = :version', $live));

        $name = $connection->fetchOne('SELECT name FROM product_translation WHERE product_id = :id AND product_version_id = :version', $live);
        static::assertSame('live name', $name, 'a discarded draft must not be merged into the live entity');
    }

    public function testCreateVersionRequiresReadPrivilege(): void
    {
        $id = $this->createProduct('live name');
        $browser = $this->getBrowser();

        TestUser::createNewTestUser($browser->getContainer()->get(Connection::class), ['category:read'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/version/product/' . $id);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('product:read', (string) $response->getContent());
    }

    public function testDeleteVersionRequiresDeletePrivilege(): void
    {
        $id = $this->createProduct('live name');
        $versionId = $this->createVersion($id);
        $browser = $this->getBrowser();

        TestUser::createNewTestUser($browser->getContainer()->get(Connection::class), ['product:read', 'version:delete'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/version/' . $versionId . '/product/' . $id);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('product:delete', (string) $response->getContent());
        static::assertSame(1, $this->countVersionedProducts($id, $versionId), 'a denied discard must keep the version');
    }

    public function testMergeRequiresUpdatePrivilege(): void
    {
        $id = $this->createProduct('live name');
        $versionId = $this->createVersion($id);
        $browser = $this->getBrowser();

        $browser->jsonRequest('PATCH', '/api/product/' . $id, ['name' => 'draft name'], ['HTTP_SW_VERSION_ID' => $versionId]);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $connection = $browser->getContainer()->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:read'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/version/merge/product/' . $versionId);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('product:update', (string) $response->getContent());
        static::assertSame('live name', $this->fetchLiveProductName($id), 'a denied merge must not change the live entity');

        TestUser::createNewTestUser($connection, ['product:read', 'product:update'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/version/merge/product/' . $versionId);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame('draft name', $this->fetchLiveProductName($id));
    }

    public function testMergeRejectsAVersionOfAnotherEntity(): void
    {
        $id = $this->createProduct('live name');
        $versionId = $this->createVersion($id);
        $browser = $this->getBrowser();

        $browser->jsonRequest('PATCH', '/api/product/' . $id, ['name' => 'draft name'], ['HTTP_SW_VERSION_ID' => $versionId]);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        TestUser::createNewTestUser($browser->getContainer()->get(Connection::class), ['category:read', 'category:update'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/version/merge/category/' . $versionId);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(ApiException::API_VERSION_ENTITY_MISMATCH, $content['errors'][0]['code']);
        static::assertSame('live name', $this->fetchLiveProductName($id), 'a category privilege must not publish a product draft');
        static::assertSame(1, $this->countVersionedProducts($id, $versionId), 'a rejected merge must keep the version');
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('versionHistoryWriteProvider')]
    public function testVersionHistoryIsNotWritableThroughTheApi(string $path, array $payload): void
    {
        $browser = $this->getBrowser();

        $browser->jsonRequest('POST', $path, $payload);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function versionHistoryWriteProvider(): iterable
    {
        yield 'a commit cannot be injected into a version' => ['/api/version-commit', ['versionId' => Uuid::randomHex()]];
        yield 'a recorded change cannot be injected into a commit' => ['/api/version-commit-data', [
            'commit' => ['versionId' => Uuid::randomHex()],
            'entityName' => 'product',
            'entityId' => ['id' => Uuid::randomHex()],
            'action' => 'update',
            'payload' => '{}',
        ]];
    }

    private function createProduct(string $name): string
    {
        $id = Uuid::randomHex();
        $browser = $this->getBrowser();

        $browser->jsonRequest('POST', '/api/product', [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $name,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ]);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        return $id;
    }

    private function createVersion(string $productId): string
    {
        $browser = $this->getBrowser();

        $browser->jsonRequest('POST', '/api/_action/version/product/' . $productId);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $versionId = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['versionId'];
        static::assertIsString($versionId);

        return $versionId;
    }

    private function countVersionedProducts(string $productId, string $versionId): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM product WHERE id = :id AND version_id = :version',
            ['id' => Uuid::fromHexToBytes($productId), 'version' => Uuid::fromHexToBytes($versionId)]
        );
    }

    private function fetchLiveProductName(string $productId): string
    {
        $name = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT name FROM product_translation WHERE product_id = :id AND product_version_id = :version AND language_id = :language',
            ['id' => Uuid::fromHexToBytes($productId), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'language' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
        static::assertIsString($name);

        return $name;
    }
}
