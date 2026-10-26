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

    public function testCloneCannotUpdateSharedTaxWithoutPermission(): void
    {
        ['productId' => $productId, 'taxId' => $taxId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read'])->authorizeBrowser($browser);
        $productCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'tax' => ['id' => $taxId, 'taxRate' => 0]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('tax:update', (string) $response->getContent());
        static::assertSame(19.0, (float) $connection->fetchOne('SELECT tax_rate FROM tax WHERE id = :id', ['id' => Uuid::fromHexToBytes($taxId)]));
        static::assertSame($productCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product'));
    }

    public function testCloneCanUpdateSharedTaxWithPermission(): void
    {
        ['productId' => $productId, 'taxId' => $taxId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read', 'tax:update'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'tax' => ['id' => $taxId, 'taxRate' => 0]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(0.0, (float) $connection->fetchOne('SELECT tax_rate FROM tax WHERE id = :id', ['id' => Uuid::fromHexToBytes($taxId)]));
    }

    public function testCloneCannotCreateNestedTaxWithoutPermission(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read'])->authorizeBrowser($browser);
        $taxCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM tax');
        $productCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'tax' => ['name' => 'New clone tax', 'taxRate' => 7]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('tax:create', (string) $response->getContent());
        static::assertSame($taxCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM tax'));
        static::assertSame($productCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product'));
    }

    public function testCloneCanCreateNestedTaxWithPermission(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read', 'tax:create'])->authorizeBrowser($browser);
        $taxCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM tax');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'tax' => ['name' => 'New clone tax', 'taxRate' => 7]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame($taxCount + 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM tax'));
    }

    public function testCloneDoesNotAllowOverwritingNestedMediaFileSize(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        $productCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product');
        $mediaCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM media');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'media' => [['media' => ['fileSize' => 1]]]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('/media/0/media/fileSize', $data['errors'][0]['source']['pointer']);
        static::assertSame($productCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product'));
        static::assertSame($mediaCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM media'));
    }

    public function testCloneCopiesProtectedMediaValuesButRejectsExplicitSameValue(): void
    {
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $mediaId = Uuid::randomHex();
        $container->get('media.repository')->create([['id' => $mediaId, 'fileSize' => 10]], Context::createDefaultContext());
        TestUser::createNewTestUser($container->get(Connection::class), ['media:create', 'media:read'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/media/' . $mediaId, ['overwrites' => ['fileName' => 'copied-media']]);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(10, (int) $container->get(Connection::class)->fetchOne('SELECT file_size FROM media WHERE id = :id', ['id' => Uuid::fromHexToBytes($data['id'])]));

        $browser->jsonRequest('POST', '/api/_action/clone/media/' . $mediaId, ['overwrites' => ['fileSize' => 10]]);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testCloneUsesParentCreatePermissionForTranslatedOverwrites(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        TestUser::createNewTestUser($browser->getContainer()->get(Connection::class), ['product:create', 'product:read'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'translations' => ['en-GB' => ['name' => 'Translated clone']]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testCloneCopiesAssociationsWithoutRequiringTheirWritePermissions(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $container->get('product.repository')->update([[
            'id' => $productId,
            'media' => [['media' => ['id' => Uuid::randomHex(), 'fileSize' => 10]]],
        ]], Context::createDefaultContext());
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read'])->authorizeBrowser($browser);
        $mediaCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM media');
        $associationCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product_media');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex()],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame($mediaCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM media'));
        static::assertSame($associationCount + 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_media'));
    }

    public function testCloneCanOverrideCopiedAssociationWithoutResubmittingRequiredFields(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $mediaId = Uuid::randomHex();
        $container->get('product.repository')->update([[
            'id' => $productId,
            'media' => [['position' => 0, 'media' => ['id' => $mediaId, 'fileSize' => 10]]],
        ]], Context::createDefaultContext());
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read', 'product_media:create'])->authorizeBrowser($browser);
        $mediaCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM media');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'media' => [['position' => 1]]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, (int) $connection->fetchOne('SELECT position FROM product_media WHERE product_id = :product AND media_id = :media', ['product' => Uuid::fromHexToBytes($data['id']), 'media' => Uuid::fromHexToBytes($mediaId)]));
        static::assertSame($mediaCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM media'));
    }

    public function testCloneCannotOverrideCopiedAssociationWithoutCreatePermission(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $container->get('product.repository')->update([[
            'id' => $productId,
            'media' => [['position' => 0, 'media' => ['id' => Uuid::randomHex(), 'fileSize' => 10]]],
        ]], Context::createDefaultContext());
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read'])->authorizeBrowser($browser);
        $associationCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product_media');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'media' => [['position' => 1]]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('product_media:create', (string) $response->getContent());
        static::assertSame($associationCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_media'));
    }

    public function testCloneCannotUpdateManyToManyTargetWithoutPermission(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $categoryId = Uuid::randomHex();
        $container->get('category.repository')->create([['id' => $categoryId, 'name' => 'Original category']], Context::createDefaultContext());
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read', 'product_category:create'])->authorizeBrowser($browser);
        $productCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => [
                'productNumber' => Uuid::randomHex(),
                'categories' => [['id' => $categoryId, 'name' => 'Changed category']],
            ],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('category:update', (string) $response->getContent());
        static::assertSame('Original category', $connection->fetchOne('SELECT name FROM category_translation WHERE category_id = :id AND language_id = :language', ['id' => Uuid::fromHexToBytes($categoryId), 'language' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]));
        static::assertSame($productCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product'));
    }

    public function testCloneCannotUpdateCopiedManyToManyTargetWhenOverwriteOmitsItsId(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $container->get('category.repository')->create([['id' => $categoryId, 'name' => 'Original category']], $context);
        $container->get('product.repository')->update([['id' => $productId, 'categories' => [['id' => $categoryId]]]], $context);
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read', 'product_category:create', 'category:create'])->authorizeBrowser($browser);
        $productCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'categories' => [['name' => 'Changed category']]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('category:update', (string) $response->getContent());
        static::assertSame('Original category', $connection->fetchOne('SELECT name FROM category_translation WHERE category_id = :id AND language_id = :language', ['id' => Uuid::fromHexToBytes($categoryId), 'language' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]));
        static::assertSame($productCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product'));
    }

    public function testCloneChecksLiveManyToManyTargetWhenCloningDraftProduct(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $container->get('category.repository')->create([['id' => $categoryId, 'name' => 'Original category']], $context);
        $productRepository = $container->get('product.repository');
        $productRepository->update([['id' => $productId, 'categories' => [['id' => $categoryId]]]], $context);
        $versionId = $productRepository->createVersion($productId, $context);
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read', 'product_category:create', 'category:create'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'categories' => [['name' => 'Changed category']]],
        ], ['HTTP_SW_VERSION_ID' => $versionId]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('category:update', (string) $response->getContent());
        static::assertSame('Original category', $connection->fetchOne('SELECT name FROM category_translation WHERE category_id = :id AND language_id = :language', ['id' => Uuid::fromHexToBytes($categoryId), 'language' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]));
    }

    public function testCloneCanUpdateCopiedManyToManyTargetWhenOverwriteOmitsItsId(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $container->get('category.repository')->create([['id' => $categoryId, 'name' => 'Original category']], $context);
        $container->get('product.repository')->update([['id' => $productId, 'categories' => [['id' => $categoryId]]]], $context);
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read', 'product_category:create', 'category:update'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'categories' => [['name' => 'Changed category']]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame('Changed category', $connection->fetchOne('SELECT name FROM category_translation WHERE category_id = :id AND language_id = :language', ['id' => Uuid::fromHexToBytes($categoryId), 'language' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]));
    }

    public function testCloneCannotLinkManyToManyTargetWithoutMappingCreatePermission(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $categoryId = Uuid::randomHex();
        $container->get('category.repository')->create([['id' => $categoryId, 'name' => 'Linked category']], Context::createDefaultContext());
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read'])->authorizeBrowser($browser);
        $associationCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product_category');

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'categories' => [['id' => $categoryId]]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('product_category:create', (string) $response->getContent());
        static::assertSame($associationCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_category'));
    }

    public function testCloneCanLinkManyToManyTargetWithoutTargetWritePermission(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $categoryId = Uuid::randomHex();
        $container->get('category.repository')->create([['id' => $categoryId, 'name' => 'Linked category']], Context::createDefaultContext());
        $connection = $container->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read', 'product_category:create'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'categories' => [['id' => $categoryId]]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_category WHERE product_id = :product AND category_id = :category', ['product' => Uuid::fromHexToBytes($data['id']), 'category' => Uuid::fromHexToBytes($categoryId)]));
    }

    public function testCloneCanCreateNestedTaxWithExplicitNewId(): void
    {
        ['productId' => $productId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read', 'tax:create'])->authorizeBrowser($browser);
        $taxId = Uuid::randomHex();

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'tax' => ['id' => $taxId, 'name' => 'New clone tax', 'taxRate' => 7]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(7.0, (float) $connection->fetchOne('SELECT tax_rate FROM tax WHERE id = :id', ['id' => Uuid::fromHexToBytes($taxId)]));
    }

    public function testTrustedSystemCloneCanOverwriteProtectedValues(): void
    {
        $container = $this->getBrowser()->getContainer();
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $container->get('media.repository')->create([['id' => $mediaId, 'fileSize' => 10]], $context);
        $cloned = $container->get('media.repository')->clone($mediaId, $context, null, new CloneBehavior(['fileSize' => 20]));
        $event = $cloned->getEventByEntityName('media');
        static::assertNotNull($event);
        $id = $event->getIds()[0];
        static::assertIsString($id);
        static::assertSame(20, (int) $container->get(Connection::class)->fetchOne('SELECT file_size FROM media WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]));
    }

    public function testCloneCannotBypassFieldProtectionThroughExtensions(): void
    {
        $browser = $this->getBrowser();
        $container = $browser->getContainer();
        $mediaId = Uuid::randomHex();
        $container->get('media.repository')->create([['id' => $mediaId, 'fileSize' => 10]], Context::createDefaultContext());

        $browser->jsonRequest('POST', '/api/_action/clone/media/' . $mediaId, [
            'overwrites' => ['extensions' => ['fileSize' => 1]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(10, (int) $container->get(Connection::class)->fetchOne('SELECT file_size FROM media WHERE id = :id', ['id' => Uuid::fromHexToBytes($mediaId)]));
    }

    public function testCloneCannotBypassAssociationAclThroughExtensions(): void
    {
        ['productId' => $productId, 'taxId' => $taxId] = $this->createProductWithTax();
        $browser = $this->getBrowser();
        $connection = $browser->getContainer()->get(Connection::class);
        TestUser::createNewTestUser($connection, ['product:create', 'product:read'])->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/_action/clone/product/' . $productId, [
            'overwrites' => ['productNumber' => Uuid::randomHex(), 'extensions' => ['tax' => ['id' => $taxId, 'taxRate' => 0]]],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('tax:update', (string) $response->getContent());
        static::assertSame(19.0, (float) $connection->fetchOne('SELECT tax_rate FROM tax WHERE id = :id', ['id' => Uuid::fromHexToBytes($taxId)]));
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
            'name' => 'Clone ACL test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false]],
            'tax' => ['id' => $taxId, 'name' => 'Shared clone tax', 'taxRate' => 19],
        ]], Context::createDefaultContext());

        return ['productId' => $productId, 'taxId' => $taxId];
    }
}
