<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerDeleteTest extends TestCase
{
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id from language list of the sales channel with id "%s".';

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

        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'product', $id);

        $this->getBrowser()->jsonRequest('DELETE', '/api/product/' . $id);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $this->assertEntityNotExists($this->getBrowser(), 'product', $id);
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

        $browser->jsonRequest('POST', '/api/tax', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $browser->jsonRequest('DELETE', '/api/tax/' . $id, ['name' => 'foo']);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());

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

        $this->getBrowser()->jsonRequest('POST', '/api/country', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'country', $id);
        $this->assertEntityExists($this->getBrowser(), 'country-state', $stateId);

        $this->getBrowser()->jsonRequest('DELETE', '/api/country/' . $id . '/states/' . $stateId, $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

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

        $browser->jsonRequest('POST', '/api/country', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'country', $id);
        $this->assertEntityExists($browser, 'country-state', $stateId);

        $browser->jsonRequest('DELETE', '/api/country/' . $id . '/states/' . $stateId, $data);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $this->assertEntityExists($browser, 'country', $id);
        $this->assertEntityExists($browser, 'country-state', $stateId);
    }

    public function testDeleteManyToOne(): void
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
        $connection = static::getContainer()->get(Connection::class);

        // Roll back the transaction that the trait started
        $connection->rollBack();

        $connection->executeStatement($dropStatement);
        $connection->executeStatement($namedOptionalGroupStatement);
        $connection->executeStatement($namedStatement);

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
        $this->getBrowser()->jsonRequest('POST', '/api/named', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/named/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'named', $id);
        $this->assertEntityExists($this->getBrowser(), 'named-optional-group', $groupId);

        $this->getBrowser()->jsonRequest('DELETE', '/api/named/' . $id . '/optional-group/' . $groupId);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $this->assertEntityExists($this->getBrowser(), 'named', $id);
        $this->assertEntityNotExists($this->getBrowser(), 'named-optional-group', $groupId);

        $connection->executeStatement($dropStatement);

        // Start a new transaction for the trait
        $connection->beginTransaction();
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

        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'product', $id);
        $this->assertEntityExists($this->getBrowser(), 'category', $category);

        $this->getBrowser()->jsonRequest('DELETE', '/api/product/' . $id . '/categories/' . $category);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $a = static::getContainer()
            ->get(Connection::class)
            ->executeQuery(
                'SELECT * FROM product_category WHERE product_id = :pid AND category_id = :cid',
                ['pid' => Uuid::fromHexToBytes($id), 'cid' => Uuid::fromHexToBytes($category)]
            )->fetchAllAssociative();
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
        $browser->jsonRequest('POST', '/api/product', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'product', $id);
        $this->assertEntityExists($browser, 'category', $category);

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['product:read', 'category:read']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('DELETE', '/api/product/' . $id . '/categories/' . $category);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $a = static::getContainer()->get(Connection::class)->executeQuery('SELECT * FROM product_category WHERE product_id = :pid AND category_id = :cid', ['pid' => Uuid::fromHexToBytes($id), 'cid' => Uuid::fromHexToBytes($category)])->fetchAllAssociative();
        static::assertNotEmpty($a);

        $this->assertEntityExists($browser, 'product', $id);
        $this->assertEntityExists($browser, 'category', $category);
    }

    public function testPreventDeletionOfDefaultSalesChannelLanguageFromLanguageList(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createSalesChannel($salesChannelId);

        $browser = $this->getBrowser();
        $browser->jsonRequest('DELETE', '/api/sales-channel/' . $salesChannelId . '/languages/' . Defaults::LANGUAGE_SYSTEM);

        $response = $browser->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $error = $content['errors'][0];

        static::assertSame(\sprintf(self::DELETE_VALIDATION_MESSAGE, $salesChannelId), $error['detail']);
    }

    private function createSalesChannel(string $id): void
    {
        $data = $this->getSalesChannelData($id);

        static::getContainer()->get('sales_channel.repository')->create([$data], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function getSalesChannelData(string $salesChannelId, string $languageId = Defaults::LANGUAGE_SYSTEM): array
    {
        return [
            'id' => $salesChannelId,
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
            'languages' => [['id' => $languageId]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];
    }
}
