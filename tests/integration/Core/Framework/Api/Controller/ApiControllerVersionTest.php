<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
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

        static::assertNotEmpty($response->headers->get('Location'));

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
        static::assertNotEmpty($response->headers->get('Location'));
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
        static::assertEmpty($response);

        $this->assertEntityExists($browser, 'product', $id);

        $actions = static::getContainer()->get(Connection::class)->fetchFirstColumn(
            'SELECT commit_data.action
             FROM version_commit_data AS commit_data
             INNER JOIN version_commit ON version_commit.id = commit_data.version_commit_id
             WHERE version_commit.version_id = :version',
            ['version' => Uuid::fromHexToBytes($versionId)]
        );

        static::assertNotContains('delete', $actions, 'a discard must not be recorded as a deletion that merge() would replay');

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

    public function testMergeOfADiscardedVersionKeepsTheLiveEntity(): void
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

        // The discard removed the version row. Recreating it puts the merge below into the window
        // where a client discards a version while a merge of the same version still runs.
        static::getContainer()->get('version.repository')->create([['id' => $versionId]], Context::createDefaultContext());

        $browser->jsonRequest('POST', '/api/_action/version/merge/product/' . $versionId);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $connection = static::getContainer()->get(Connection::class);
        $live = ['id' => Uuid::fromHexToBytes($id), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];

        static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM product WHERE id = :id AND version_id = :version', $live));

        $name = $connection->fetchOne('SELECT name FROM product_translation WHERE product_id = :id AND product_version_id = :version', $live);
        static::assertSame('draft name', $name, 'the merge still applied the draft edit');
    }
}
