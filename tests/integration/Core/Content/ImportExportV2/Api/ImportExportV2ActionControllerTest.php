<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExportV2\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1774617000CreateImportExportV2Tables;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportV2ActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private static bool $schemaInitialized = false;

    /**
     * @var EntityRepository<EntityCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<EntityCollection>
     */
    private EntityRepository $taxRepository;

    /**
     * @var EntityRepository<EntityCollection>
     */
    private EntityRepository $profileRepository;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::ensureSchema();
    }

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->taxRepository = static::getContainer()->get('tax.repository');
        $this->profileRepository = static::getContainer()->get('import_export_v2_profile.repository');

        $this->seedProfiles();
    }

    public function testStartImportRequiresAuthentication(): void
    {
        $browser = $this->createClient(authorized: false);
        $browser->jsonRequest('POST', '/api/_action/import-export-v2/import', [
            'profileName' => 'product-json',
            'inputContents' => '[]',
        ]);

        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
    }

    public function testListProfilesReturnsSeededProfiles(): void
    {
        $browser = $this->createClient();
        $browser->request('GET', '/api/_action/import-export-v2/profiles');

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);
        $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(2, $payload['profiles']);
        $names = array_column($payload['profiles'], 'name');
        sort($names);
        static::assertSame(['product-csv', 'product-json'], $names);
    }

    public function testStartImportEndpointQueuesRunAndProcessesImportedProduct(): void
    {
        $originalTaxId = $this->createTax('Original tax', 19.0);
        $updatedTaxId = $this->createTax('Updated tax', 7.0);
        $productId = $this->createProduct('SW10001', 'Original product', $originalTaxId);

        $browser = $this->createClient();
        $browser->jsonRequest('POST', '/api/_action/import-export-v2/import', [
            'profileName' => 'product-json',
            'inputFileName' => 'products.json',
            'inputMimeType' => 'application/json',
            'inputContents' => json_encode([[
                'entity' => 'product',
                'identifier' => ['productNumber' => 'SW10001'],
                'payload' => [
                    'active' => true,
                    'stock' => 15,
                    'translations' => [
                        'DEFAULT' => [
                            'name' => 'Updated product',
                        ],
                    ],
                    'tax' => [
                        'id' => $updatedTaxId,
                    ],
                ],
            ]], \JSON_THROW_ON_ERROR),
        ]);

        static::assertSame(Response::HTTP_ACCEPTED, $browser->getResponse()->getStatusCode());
        $response = $this->decodeBrowserJson($browser);
        static::assertSame('queued', $response['run']['state']);
        static::assertSame(1, $this->getDispatchedMessageCount(\Shopware\Core\Content\ImportExportV2\Job\Message\ProcessRunMessage::class));

        $this->runWorker();

        $browser->request('GET', '/api/_action/import-export-v2/run/' . $response['run']['id']);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        $run = $this->decodeBrowserJson($browser)['run'];

        static::assertSame('completed', $run['state']);
        static::assertSame(1, $run['processed']);
        static::assertSame(1, $run['succeeded']);
        static::assertNotEmpty($run['inputArtifactId']);

        $browser->request('GET', '/api/_action/import-export-v2/artifact/' . $run['inputArtifactId']);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        $artifact = $this->decodeBrowserJson($browser)['artifact'];
        static::assertSame('products.json', $artifact['name']);

        $product = $this->productRepository->search(new Criteria([$productId]), \Shopware\Core\Framework\Context::createDefaultContext())->first();
        static::assertNotNull($product);
        static::assertSame(15, $product->jsonSerialize()['stock']);
        static::assertTrue($product->jsonSerialize()['active']);
        static::assertSame($updatedTaxId, $product->jsonSerialize()['taxId']);
        static::assertSame('Updated product', $product->jsonSerialize()['translated']['name']);
    }

    public function testStartImportEndpointProcessesMultipleChunks(): void
    {
        $originalTaxId = $this->createTax('Original tax', 19.0);
        $updatedTaxId = $this->createTax('Updated tax', 7.0);

        $this->createProduct('SW11001', 'Original one', $originalTaxId);
        $this->createProduct('SW11002', 'Original two', $originalTaxId);
        $this->createProduct('SW11003', 'Original three', $originalTaxId);

        $browser = $this->createClient();
        $browser->jsonRequest('POST', '/api/_action/import-export-v2/import', [
            'profileName' => 'product-json',
            'chunkSize' => 2,
            'inputContents' => json_encode([
                $this->createImportRecord('SW11001', 'Updated one', $updatedTaxId, 11),
                $this->createImportRecord('SW11002', 'Updated two', $updatedTaxId, 12),
                $this->createImportRecord('SW11003', 'Updated three', $updatedTaxId, 13),
            ], \JSON_THROW_ON_ERROR),
        ]);

        static::assertSame(Response::HTTP_ACCEPTED, $browser->getResponse()->getStatusCode());
        $runId = $this->decodeBrowserJson($browser)['run']['id'];

        $this->runWorker();

        $browser->request('GET', '/api/_action/import-export-v2/run/' . $runId);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        $run = $this->decodeBrowserJson($browser)['run'];

        static::assertSame('completed', $run['state']);
        static::assertSame(3, $run['processed']);
        static::assertSame(3, $run['succeeded']);
        static::assertSame(3, $run['totalRecords']);
        static::assertSame(3, $run['cursor']['offset']);
        static::assertSame(2, $run['cursor']['chunkSize']);
        static::assertGreaterThan(1, $this->getDispatchedMessageCount(\Shopware\Core\Content\ImportExportV2\Job\Message\ProcessRunMessage::class));
    }

    public function testStartImportEndpointReturnsCompletedRunWithValidationFailure(): void
    {
        $taxId = $this->createTax('Original tax', 19.0);
        $this->createProduct('SW30001', 'Original product', $taxId, 10, false);

        $browser = $this->createClient();
        $browser->jsonRequest('POST', '/api/_action/import-export-v2/import', [
            'profileName' => 'product-json',
            'inputContents' => json_encode([[
                'entity' => 'product',
                'identifier' => ['productNumber' => 'SW30001'],
                'payload' => [
                    'active' => true,
                    'stock' => 99,
                    'tax' => [
                        'id' => Uuid::randomHex(),
                    ],
                ],
            ]], \JSON_THROW_ON_ERROR),
        ]);

        static::assertSame(Response::HTTP_ACCEPTED, $browser->getResponse()->getStatusCode());
        $runId = $this->decodeBrowserJson($browser)['run']['id'];

        $this->runWorker();

        $browser->request('GET', '/api/_action/import-export-v2/run/' . $runId);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        $run = $this->decodeBrowserJson($browser)['run'];

        static::assertSame('completed', $run['state']);
        static::assertSame(1, $run['processed']);
        static::assertSame(0, $run['succeeded']);
        static::assertSame(1, $run['failed']);
        static::assertCount(1, $run['failures']);
    }

    public function testStartExportEndpointQueuesRunAndDownloadReturnsExportedArtifact(): void
    {
        $taxId = $this->createTax('Export tax', 19.0);
        $productId = $this->createProduct('SW20001', 'Export product', $taxId, 42, true);

        $browser = $this->createClient();
        $browser->jsonRequest('POST', '/api/_action/import-export-v2/export', [
            'profileName' => 'product-json',
            'recordIds' => [$productId],
        ]);

        static::assertSame(Response::HTTP_ACCEPTED, $browser->getResponse()->getStatusCode());
        $runId = $this->decodeBrowserJson($browser)['run']['id'];

        $this->runWorker();

        $browser->request('GET', '/api/_action/import-export-v2/run/' . $runId);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        $run = $this->decodeBrowserJson($browser)['run'];

        static::assertSame('completed', $run['state']);
        static::assertNotEmpty($run['outputArtifactId']);

        $browser->request('GET', '/api/_action/import-export-v2/artifact/' . $run['outputArtifactId'] . '/download');
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        static::assertSame('application/json', $browser->getResponse()->headers->get('Content-Type'));

        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('SW20001', $payload[0]['identifier']['productNumber']);
        static::assertSame('Export product', $payload[0]['payload']['translations']['DEFAULT']['name']);
        static::assertSame($taxId, $payload[0]['payload']['tax']['id']);
    }

    public function testCancelAndResumeEndpointsControlRunLifecycle(): void
    {
        $taxId = $this->createTax('Original tax', 19.0);
        $this->createProduct('SW12001', 'Original product', $taxId, 10, false);

        $browser = $this->createClient();
        $browser->jsonRequest('POST', '/api/_action/import-export-v2/import', [
            'profileName' => 'product-json',
            'chunkSize' => 1,
            'inputContents' => json_encode([
                $this->createImportRecord('SW12001', 'Updated product', $taxId, 44),
            ], \JSON_THROW_ON_ERROR),
        ]);

        static::assertSame(Response::HTTP_ACCEPTED, $browser->getResponse()->getStatusCode());
        $runId = $this->decodeBrowserJson($browser)['run']['id'];

        $browser->jsonRequest('POST', '/api/_action/import-export-v2/run/' . $runId . '/cancel');
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        static::assertSame('canceled', $this->decodeBrowserJson($browser)['run']['state']);

        $this->runWorker();

        $browser->jsonRequest('POST', '/api/_action/import-export-v2/run/' . $runId . '/resume');
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        static::assertSame('queued', $this->decodeBrowserJson($browser)['run']['state']);

        $this->runWorker();

        $browser->request('GET', '/api/_action/import-export-v2/run/' . $runId);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        $run = $this->decodeBrowserJson($browser)['run'];

        static::assertSame('completed', $run['state']);
        static::assertSame(1, $run['processed']);
        static::assertSame(1, $run['succeeded']);
    }

    public function testEndpointsReturnHelpfulErrorsForMissingInputAndUnknownRun(): void
    {
        $browser = $this->createClient();
        $browser->jsonRequest('POST', '/api/_action/import-export-v2/import', [
            'profileName' => 'product-json',
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $browser->getResponse()->getStatusCode());

        $browser->request('GET', '/api/_action/import-export-v2/run/' . Uuid::randomHex());
        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
    }

    private static function ensureSchema(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        (new Migration1774617000CreateImportExportV2Tables())->update($connection);

        self::$schemaInitialized = true;
    }

    private function seedProfiles(): void
    {
        $context = \Shopware\Core\Framework\Context::createDefaultContext();

        $this->profileRepository->upsert([
            $this->createJsonProfilePayload($this->findExistingProfileId('product-json') ?? '11111111111111111111111111111111'),
            $this->createCsvProfilePayload($this->findExistingProfileId('product-csv') ?? '22222222222222222222222222222222'),
        ], $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function createJsonProfilePayload(string $id): array
    {
        return [
            'id' => $id,
            'name' => 'product-json',
            'entity' => 'product',
            'format' => 'json',
            'identifierPaths' => ['productNumber'],
            'payloadPaths' => ['active', 'stock', 'translations.DEFAULT.name', 'tax.id'],
            'relationModes' => ['tax' => 'replace'],
            'fieldMappings' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createCsvProfilePayload(string $id): array
    {
        return [
            'id' => $id,
            'name' => 'product-csv',
            'entity' => 'product',
            'format' => 'csv',
            'identifierPaths' => ['productNumber'],
            'payloadPaths' => [
                'active',
                'stock',
                'translations.DEFAULT.name',
                'tax.id',
                'visibilities.0.salesChannelId',
                'visibilities.0.visibility',
                'categories.*.id',
            ],
            'relationModes' => [
                'tax' => 'replace',
                'visibilities' => 'replace',
                'categories' => 'replace',
            ],
            'fieldMappings' => [
                ['column' => 'product_number', 'path' => 'identifier.productNumber', 'separator' => null, 'type' => 'string'],
                ['column' => 'active', 'path' => 'payload.active', 'separator' => null, 'type' => 'bool'],
                ['column' => 'stock', 'path' => 'payload.stock', 'separator' => null, 'type' => 'int'],
                ['column' => 'name', 'path' => 'payload.translations.DEFAULT.name', 'separator' => null, 'type' => 'string'],
                ['column' => 'tax_id', 'path' => 'payload.tax.id', 'separator' => null, 'type' => 'string'],
                ['column' => 'visibility_sales_channel_id', 'path' => 'payload.visibilities.0.salesChannelId', 'separator' => null, 'type' => 'string'],
                ['column' => 'visibility_level', 'path' => 'payload.visibilities.0.visibility', 'separator' => null, 'type' => 'string'],
                ['column' => 'category_ids', 'path' => 'payload.categories.*.id', 'separator' => '|', 'type' => 'string'],
            ],
        ];
    }

    private function createTax(string $name, float $taxRate): string
    {
        $context = \Shopware\Core\Framework\Context::createDefaultContext();
        $id = Uuid::randomHex();
        $this->taxRepository->create([[
            'id' => $id,
            'name' => $name,
            'taxRate' => $taxRate,
        ]], $context);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function createImportRecord(string $productNumber, string $name, string $taxId, int $stock): array
    {
        return [
            'entity' => 'product',
            'identifier' => ['productNumber' => $productNumber],
            'payload' => [
                'active' => true,
                'stock' => $stock,
                'translations' => [
                    'DEFAULT' => [
                        'name' => $name,
                    ],
                ],
                'tax' => [
                    'id' => $taxId,
                ],
            ],
        ];
    }

    private function createProduct(
        string $productNumber,
        string $name,
        string $taxId,
        int $stock = 10,
        bool $active = true
    ): string {
        $context = \Shopware\Core\Framework\Context::createDefaultContext();
        $id = Uuid::randomHex();

        $this->productRepository->create([[
            'id' => $id,
            'name' => $name,
            'productNumber' => $productNumber,
            'stock' => $stock,
            'active' => $active,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 15.0,
                'net' => 12.5,
                'linked' => false,
            ]],
        ]], $context);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBrowserJson(\Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser $browser): array
    {
        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function findExistingProfileId(string $name): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $name));

        $profile = $this->profileRepository->search($criteria, \Shopware\Core\Framework\Context::createDefaultContext())->first();

        return $profile instanceof \Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity ? $profile->getId() : null;
    }
}
