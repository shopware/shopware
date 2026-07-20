<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerCreateTest extends TestCase
{
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const INSERT_VALIDATION_MESSAGE = 'The sales channel with id "%s" does not have a default sales channel language id in the language list.';

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

        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->jsonRequest('GET', '/api/product/' . $id);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
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

        $this->getBrowserAuthenticatedWithIntegration()->jsonRequest('POST', '/api/product', $data);
        $response = $this->getBrowserAuthenticatedWithIntegration()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $this->getBrowserAuthenticatedWithIntegration()->jsonRequest('GET', '/api/product/' . $id);
        static::assertSame(Response::HTTP_OK, $this->getBrowserAuthenticatedWithIntegration()->getResponse()->getStatusCode(), (string) $this->getBrowserAuthenticatedWithIntegration()->getResponse()->getContent());
    }

    public function testOneToManyInsert(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id];

        $this->getBrowser()->jsonRequest('POST', '/api/country', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->jsonRequest('GET', '/api/country/' . $id);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $data = [
            'id' => $id,
            'name' => 'test_state',
            'shortCode' => 'test',
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/country/' . $id . '/states/', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country-state/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->jsonRequest('GET', '/api/country/' . $id . '/states/');
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        static::assertIsArray($responseData);
        static::assertArrayHasKey('data', $responseData);
        static::assertCount(1, $responseData['data'], \sprintf('Expected country %s has only one state', $id));

        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(1, $responseData['meta']['total']);

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

        $browser->jsonRequest('POST', '/api/country', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'country', $id);

        $data = [
            'id' => $id,
            'name' => 'test_state',
            'shortCode' => 'test',
        ];

        $browser->jsonRequest('POST', '/api/country/' . $id . '/states/', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());

        $admin->authorizeBrowser($browser);

        $this->assertEntityNotExists($browser, 'country-state', $id);
    }

    public function testTranslatedPropertiesWritableWithParentDefinitionPermissions(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id];

        $this->getBrowser()->jsonRequest('POST', '/api/country', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $browser = $this->getBrowser();
        $connection = $this->getBrowser()->getContainer()->get(Connection::class);
        $user = TestUser::createNewTestUser($connection, ['country:update', 'country:read']);

        $user->authorizeBrowser($browser);

        $data = ['name' => 'not in system language'];
        $languageId = $this->getNonSystemLanguageId();
        $browser->setServerParameter('HTTP_sw-language-id', $languageId);

        $browser->jsonRequest(
            'PATCH',
            '/api/country/' . $id,
            $data
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'country', $id);
    }

    public function testCreateAndDeleteWithPermissions(): void
    {
        $connection = static::getContainer()->get(Connection::class);

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

        $browser->jsonRequest('POST', '/api/product', $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $browser->jsonRequest('DELETE', '/api/product/' . $id);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public function testTranslatedPropertiesNotWritableWithoutParentDefinitionPermissions(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id];

        $this->getBrowser()->jsonRequest('POST', '/api/country', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $browser = $this->getBrowser();
        $connection = $this->getBrowser()->getContainer()->get(Connection::class);
        $user = TestUser::createNewTestUser($connection, ['country:create', 'country:read']);

        $user->authorizeBrowser($browser);

        $data = ['name' => 'not in system language'];
        $languageId = $this->getNonSystemLanguageId();
        $browser->setServerParameter('HTTP_sw-language-id', $languageId);

        $browser->jsonRequest(
            'PATCH',
            '/api/country/' . $id,
            $data
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
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

        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), 'Create product failed id:' . $id);
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $manufacturer,
            'name' => 'Manufacturer - 1',
            'link' => 'https://www.shopware.com',
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/product/' . $id . '/manufacturer', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), 'Create manufacturer over product failed id:' . $id . "\n" . $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product-manufacturer/' . $manufacturer, $response->headers->get('Location'));

        $this->getBrowser()->jsonRequest('GET', '/api/product/' . $id . '/manufacturer');
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $responseData, (string) $this->getBrowser()->getResponse()->getContent());
        static::assertArrayHasKey(0, $responseData['data'], (string) $this->getBrowser()->getResponse()->getContent());

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

        $browser->jsonRequest('POST', '/api/product', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), 'Create product failed id:' . $id);
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $user->authorizeBrowser($browser);

        $data = [
            'id' => $manufacturer,
            'name' => 'Manufacturer - 1',
            'link' => 'https://www.shopware.com',
        ];

        $browser->jsonRequest('POST', '/api/product/' . $id . '/manufacturer', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());

        $admin->authorizeBrowser($browser);

        $this->assertEntityNotExists($browser, 'product-manufacturer', $manufacturer);

        $browser->jsonRequest('GET', '/api/product/' . $id . '/manufacturer');
        $responseData = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), 'Read manufacturer of product failed id: ' . $id . \PHP_EOL . $browser->getResponse()->getContent());

        static::assertArrayHasKey('data', $responseData, (string) $browser->getResponse()->getContent());
        static::assertArrayHasKey(0, $responseData['data'], (string) $browser->getResponse()->getContent());
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

        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $id,
            'name' => 'Category - 1',
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/product/' . $id . '/categories/', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/category/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->jsonRequest('GET', '/api/product/' . $id . '/categories/');
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
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

        $browser->jsonRequest('POST', '/api/product', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'id' => $id,
            'name' => 'Category - 1',
        ];

        $browser->jsonRequest('POST', '/api/product/' . $id . '/categories/', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());

        $admin->authorizeBrowser($browser);

        $this->assertEntityNotExists($browser, 'category', $id);

        $browser->jsonRequest('GET', '/api/product/' . $id . '/categories/');
        $responseData = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        static::assertArrayHasKey('data', $responseData);
        static::assertCount(0, $responseData['data']);
    }

    public function testPreventCreationOfSalesChannelWithoutDefaultSalesChannelLanguage(): void
    {
        $salesChannelId = Uuid::randomHex();
        $data = $this->getSalesChannelData($salesChannelId, $this->getNonSystemLanguageId());

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/sales-channel/', $data, $data);

        $response = $browser->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $error = $content['errors'][0];

        static::assertSame(\sprintf(self::INSERT_VALIDATION_MESSAGE, $salesChannelId), $error['detail']);
    }

    public function testWriteExtensionWithExtensionKey(): void
    {
        $field = (new OneToManyAssociationField('testSeoUrls', SeoUrlDefinition::class, 'sales_channel_id'))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(SalesChannelDefinition::class)->getFields()->addNewField($field);

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

        $this->getBrowser()->jsonRequest('PATCH', '/api/sales-channel/' . $salesChannelId, $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

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

        $this->getBrowser()->jsonRequest('POST', '/api/search/sales-channel', $filter);
        $response = $this->getBrowser()->getResponse();
        $result = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $data = $result['data'];

        static::assertCount(1, $data);
        static::assertArrayHasKey('extensions', $data[0]['relationships']);

        $included = $result['included'];
        static::assertCount(2, $included);

        // sort the included entities alphabetically by type
        usort($included, static fn ($a, $b) => $a['type'] <=> $b['type']);

        $extension = $included[0];
        static::assertSame('extension', $extension['type']);
        static::assertArrayHasKey('testSeoUrls', $extension['relationships']);

        $seoUrl = $included[1];
        static::assertSame('seo_url', $seoUrl['type']);
        static::assertSame('test', $seoUrl['attributes']['routeName']);

        $this->getBrowser()->jsonRequest('GET', '/api/sales-channel/' . $salesChannelId . '/extensions/seo-urls');
        $response = $this->getBrowser()->getResponse();
        $result = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $data = $result['data'];

        static::assertCount(1, $data);

        $seoUrl = $data[0];
        static::assertSame('seo_url', $seoUrl['type']);
        static::assertSame('test', $seoUrl['attributes']['routeName']);
    }

    public function testCanWriteExtensionWithoutExtensionKey(): void
    {
        $field = (new OneToManyAssociationField('testSeoUrls', SeoUrlDefinition::class, 'sales_channel_id'))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(SalesChannelDefinition::class)->getFields()->addNewField($field);

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

        $this->getBrowser()->jsonRequest('PATCH', '/api/sales-channel/' . $salesChannelId, $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

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

        $this->getBrowser()->jsonRequest('POST', '/api/search/sales-channel', $filter);
        $response = $this->getBrowser()->getResponse();
        $result = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $data = $result['data'];

        static::assertCount(1, $data);
        static::assertArrayHasKey('extensions', $data[0]['relationships']);

        $included = $result['included'];
        static::assertCount(2, $included);

        // sort the included entities alphabetically by type
        usort($included, static fn ($a, $b) => $a['type'] <=> $b['type']);

        $extension = $included[0];
        static::assertSame('extension', $extension['type']);
        static::assertArrayHasKey('testSeoUrls', $extension['relationships']);

        $seoUrls = $included[1];
        static::assertSame('seo_url', $seoUrls['type']);
        static::assertSame('test', $seoUrls['attributes']['routeName']);

        $this->getBrowser()->jsonRequest('GET', '/api/sales-channel/' . $salesChannelId . '/extensions/seo-urls');
        $response = $this->getBrowser()->getResponse();
        $result = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $data = $result['data'];

        static::assertCount(1, $data);

        $seoUrl = $data[0];
        static::assertSame('seo_url', $seoUrl['type']);
        static::assertSame('test', $seoUrl['attributes']['routeName']);
    }

    public function testInvalidWriteInputExceptionIsConvertedToBadRequestOnCreate(): void
    {
        $entityName = 'product-feature-set';

        $client = $this->getBrowser();

        $client->jsonRequest('POST', '/api/' . $entityName, [2 => 'test']);

        $response = $client->getResponse()->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, (int) $response['errors'][0]['status']);
        static::assertSame('Invalid payload. Should be associative array', $response['errors'][0]['detail']);
    }

    public function testParentChildLocation(): void
    {
        $childId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $data = [
            'id' => $childId,
            'name' => 'Child Language',
            'localeId' => $this->getLocaleIdOfSystemLanguage(),
            'active' => true,
            'parent' => [
                'id' => $parentId,
                'name' => 'Parent Language',
                'locale' => [
                    'code' => 'de-DE-' . Uuid::randomHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'translationCode' => [
                    'code' => 'de-DE-' . Uuid::randomHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'active' => true,
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/language', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/language/' . $childId, $response->headers->get('Location'));
    }

    public function testDirectlyAddMappingEntry(): void
    {
        $productId = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 8300, 'net' => 8300, 'linked' => false]],
            'stock' => 50,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);

        $categoryId = Uuid::randomHex();
        $data = ['id' => $categoryId, 'name' => 'test category'];
        $this->getBrowser()->jsonRequest('POST', '/api/category', $data);

        $mapping = [
            'productId' => $productId,
            'categoryId' => $categoryId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/product-category', $mapping);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $repo = static::getContainer()->get(ProductDefinition::ENTITY_NAME . '.repository');
        $criteria = new Criteria([$productId]);

        $product = $repo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $product);

        static::assertSame([
            $categoryId,
        ], $product->getCategoryIds());
    }

    public function testDirectlyAddMappingEntryWithResponse(): void
    {
        $productId = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 8300, 'net' => 8300, 'linked' => false]],
            'stock' => 50,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);

        $categoryId = Uuid::randomHex();
        $data = ['id' => $categoryId, 'name' => 'test category'];
        $this->getBrowser()->jsonRequest('POST', '/api/category', $data);

        $mapping = [
            'productId' => $productId,
            'categoryId' => $categoryId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/product-category?_response=1', $mapping);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $repo = static::getContainer()->get(ProductDefinition::ENTITY_NAME . '.repository');
        $criteria = new Criteria([$productId]);

        $product = $repo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $product);

        static::assertSame([
            $categoryId,
        ], $product->getCategoryIds());
    }

    private function getNonSystemLanguageId(): string
    {
        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM),
            ]
        ));
        $criteria->setLimit(1);

        $id = $languageRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($id);

        return $id;
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
