<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
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
class ApiControllerCloneTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testCloneEntity(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test tax clone',
            'taxRate' => 15,
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/tax', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->jsonRequest('GET', '/api/tax/' . $id);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $tax = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $tax);
        static::assertSame($id, $tax['data']['id']);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/clone/tax/' . $id, $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('id', $data);
        static::assertNotSame($id, $data['id']);

        $newId = $data['id'];
        $this->getBrowser()->jsonRequest('GET', '/api/tax/' . $newId);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(15.0, $data['data']['attributes']['taxRate']);
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
        $browser->jsonRequest('POST', '/api/tax', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $connection = $browser->getContainer()->get(Connection::class);
        TestUser::createNewTestUser(
            $connection,
            ['tax:read']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('GET', '/api/tax/' . $id);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $tax = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $tax);
        static::assertSame($id, $tax['data']['id']);

        $browser->jsonRequest('POST', '/api/_action/clone/tax/' . $id, $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testCloneDoesNotAllowOverwritingMediaFileSize(): void
    {
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        $testUser = TestUser::createNewTestUser($connection, ['media:create']);
        $mediaId = $connection->fetchOne('SELECT avatar_id FROM user WHERE id = :id', ['id' => Uuid::fromHexToBytes($testUser->getUserId())]);
        static::assertIsString($mediaId);
        $testUser->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/media/' . Uuid::fromBytesToHex($mediaId), [
            'overwrites' => [
                'fileName' => 'cloned-media',
                'fileSize' => 1,
            ],
            'cloneChildren' => false,
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testCloneRejectsSharedTaxAssociationOverwrite(): void
    {
        ['productId' => $productId, 'taxId' => $taxId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        $productCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'tax' => ['id' => $taxId, 'taxRate' => 0]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('/tax', $data['errors'][0]['source']['pointer']);
        static::assertStringContainsString('Association overwrites are not supported', $data['errors'][0]['detail']);
        static::assertSame(19.0, (float) $connection->fetchOne('SELECT tax_rate FROM tax WHERE id = :id', ['id' => Uuid::fromHexToBytes($taxId)]));
        static::assertSame($productCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product'));
    }

    public function testCloneRejectsPositionalOverwriteOfMultipleCopiedMedia(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $container->get('product.repository')->update([[
            'id' => $productId,
            'media' => [
                ['position' => 0, 'media' => ['id' => Uuid::randomHex(), 'fileSize' => 10]],
                ['position' => 1, 'media' => ['id' => Uuid::randomHex(), 'fileSize' => 20]],
            ],
        ]], Context::createDefaultContext());
        $connection = $container->get(Connection::class);
        $productCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product');
        $associationCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product_media');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'media' => [['position' => 5]]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('/media', $data['errors'][0]['source']['pointer']);
        static::assertSame($productCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product'));
        static::assertSame($associationCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_media'));
        static::assertSame([0, 1], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM product_media WHERE product_id = :id ORDER BY position', ['id' => Uuid::fromHexToBytes($productId)])));
    }

    public function testCloneRejectsIdOnlyManyToManyOverwrite(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $categoryId = Uuid::randomHex();
        $container->get('category.repository')->create([['id' => $categoryId, 'name' => 'Existing category']], Context::createDefaultContext());
        $connection = $container->get(Connection::class);
        $associationCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product_category');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'categories' => [['id' => $categoryId]]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('/categories', $data['errors'][0]['source']['pointer']);
        static::assertSame($associationCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_category'));
    }

    public function testCloneRejectsExplicitTranslationAssociationOverwrite(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'translations' => ['en-GB' => ['name' => 'Changed name']]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('/translations', $data['errors'][0]['source']['pointer']);
    }

    public function testCloneAllowsRootJsonTranslatedAndForeignKeyOverwrites(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $taxId = Uuid::randomHex();
        $container->get('tax.repository')->create([['id' => $taxId, 'name' => 'Other tax', 'taxRate' => 7]], Context::createDefaultContext());
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => [
                'productNumber' => Uuid::randomHex(),
                'name' => [Defaults::LANGUAGE_SYSTEM => 'Allowed clone'],
                'taxId' => $taxId,
                'variantListingConfig' => ['displayParent' => true, 'configuratorGroupConfig' => []],
            ],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $cloneId = Uuid::fromHexToBytes($data['id']);
        static::assertSame(Uuid::fromHexToBytes($taxId), $connection->fetchOne('SELECT tax_id FROM product WHERE id = :id', ['id' => $cloneId]));
        static::assertSame('Allowed clone', $connection->fetchOne('SELECT name FROM product_translation WHERE product_id = :id AND language_id = :language', ['id' => $cloneId, 'language' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]));
        $config = json_decode((string) $connection->fetchOne('SELECT variant_listing_config FROM product WHERE id = :id', ['id' => $cloneId]), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $config['displayParent']);
        static::assertSame([], $config['configuratorGroupConfig']);
    }

    public function testCloneStillCopiesMultipleMediaWithProtectedValues(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $mediaIds = [Uuid::randomHex(), Uuid::randomHex()];
        $container->get('product.repository')->update([[
            'id' => $productId,
            'media' => [
                ['position' => 0, 'media' => ['id' => $mediaIds[0], 'fileSize' => 10]],
                ['position' => 1, 'media' => ['id' => $mediaIds[1], 'fileSize' => 20]],
            ],
        ]], Context::createDefaultContext());
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read'])->authorizeBrowser($browser);
        $mediaCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM media');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex()],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(array_map(Uuid::fromHexToBytes(...), $mediaIds), $connection->fetchFirstColumn('SELECT media_id FROM product_media WHERE product_id = :id ORDER BY position', ['id' => Uuid::fromHexToBytes($data['id'])]));
        static::assertSame($mediaCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM media'));
        static::assertSame(10, (int) $connection->fetchOne('SELECT file_size FROM media WHERE id = :id', ['id' => Uuid::fromHexToBytes($mediaIds[0])]));
        static::assertSame(20, (int) $connection->fetchOne('SELECT file_size FROM media WHERE id = :id', ['id' => Uuid::fromHexToBytes($mediaIds[1])]));
    }

    public function testCloneRejectsAssociationOverwriteThroughExtensions(): void
    {
        ['productId' => $productId, 'taxId' => $taxId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'extensions' => ['tax' => ['id' => $taxId, 'taxRate' => 0]]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('/extensions/tax', $data['errors'][0]['source']['pointer']);
        $connection = $browser->getContainer()->get(Connection::class);
        static::assertSame(19.0, (float) $connection->fetchOne('SELECT tax_rate FROM tax WHERE id = :id', ['id' => Uuid::fromHexToBytes($taxId)]));
    }

    public function testCloneRetainsRootFieldProtectionThroughExtensions(): void
    {
        $browser = $this->getBrowser();
        $mediaId = Uuid::randomHex();
        $browser->getContainer()->get('media.repository')->create([['id' => $mediaId, 'fileSize' => 10]], Context::createDefaultContext());
        $browser->jsonRequest('POST', '/api/_action/clone/media/' . $mediaId, [
            'overwrites' => ['extensions' => ['fileSize' => 1]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('/extensions/fileSize', $data['errors'][0]['source']['pointer']);
    }

    public function testTrustedSystemCloneCanOverwriteProtectedRootValues(): void
    {
        $container = $this->getBrowser()->getContainer();
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $container->get('media.repository')->create([['id' => $mediaId, 'fileSize' => 10]], $context);
        $cloned = $container->get('media.repository')->clone($mediaId, $context, null, new CloneBehavior(['fileSize' => 20]));
        $cloneId = $cloned->getEventByEntityName('media')?->getIds()[0];
        static::assertIsString($cloneId);
        $fileSize = $container->get(Connection::class)->fetchOne('SELECT file_size FROM media WHERE id = :id', ['id' => Uuid::fromHexToBytes($cloneId)]);
        static::assertSame(20, (int) $fileSize);
    }

    public function testCloneDoesNotAllowCloningUser(): void
    {
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        $testUser = TestUser::createNewTestUser($connection, ['user:create']);
        $countBefore = (int) $connection->fetchOne('SELECT COUNT(*) FROM user');
        $testUser->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/user/' . $testUser->getUserId());

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame($countBefore, (int) $connection->fetchOne('SELECT COUNT(*) FROM user'));
    }

    public function testCloneDoesNotAllowCloningIntegration(): void
    {
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        $integrationId = Uuid::randomHex();
        $browser->getContainer()->get('integration.repository')->create([[
            'id' => $integrationId,
            'label' => 'Clone protected integration',
            'accessKey' => 'clone-protected-access-key',
            'secretAccessKey' => 'clone-protected-secret-key',
        ]], Context::createDefaultContext());

        $testUser = TestUser::createNewTestUser($connection, ['integration:create']);
        $countBefore = (int) $connection->fetchOne('SELECT COUNT(*) FROM integration');
        $testUser->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/integration/' . $integrationId);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame($countBefore, (int) $connection->fetchOne('SELECT COUNT(*) FROM integration'));
    }

    /**
     * @return array{productId: string, taxId: string}
     */
    private function createProductWithTax(): array
    {
        $productId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $this->getBrowser()->getContainer()->get('product.repository')->create([[
            'id' => $productId,
            'name' => 'Clone source',
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false]],
            'tax' => ['id' => $taxId, 'name' => 'Shared clone tax', 'taxRate' => 19],
        ]], Context::createDefaultContext());

        return ['productId' => $productId, 'taxId' => $taxId];
    }
}
