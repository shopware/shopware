<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('system-settings')]
class ImportExportActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('import_export_profile.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testUploadingFileWithUnexpectedMimeTypeFails(): void
    {
        $data = $this->prepareImportExportActionControllerTestData(2);
        $this->repository->create(array_values($data), $this->context);

        foreach ($data as $entry) {
            $client = $this->getBrowser();
            $client->request(
                'POST',
                '/api/_action/import-export/prepare',
                ['profileId' => $entry['id'], 'expireDate' => date('Y-m-d H:i:s')],
                ['file' => $this->getUploadFile('text/html', 'test.xml')],
                ['Content-Type' => 'multipart/formdata']
            );

            $response = $client->getResponse();

            static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        }
    }

    public function testUploadingFileSuccess(): void
    {
        $data = $this->prepareImportExportActionControllerTestData(2);

        $this->repository->create(array_values($data), $this->context);

        foreach ($data as $entry) {
            $client = $this->getBrowser();
            $client->request(
                'POST',
                '/api/_action/import-export/prepare',
                ['profileId' => $entry['id'], 'expireDate' => date('Y-m-d H:i:s')],
                ['file' => $this->getUploadFile($entry['fileType'])],
                ['Content-Type' => 'multipart/formdata']
            );

            $response = $client->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        }
    }

    public function testExportReadPrivileges(): void
    {
        $data = $this->prepareImportExportActionControllerTestData();

        $this->repository->create(array_values($data), $this->context);

        foreach ($data as $entry) {
            $client = $this->getBrowser();
            TestUser::createNewTestUser(
                $client->getContainer()->get(Connection::class),
                []
            )->authorizeBrowser($client);
            $client->request(
                'POST',
                '/api/_action/import-export/prepare',
                ['profileId' => $entry['id'], 'expireDate' => date('Y-m-d H:i:s')],
                [],
                ['Content-Type' => 'multipart/formdata']
            );

            $response = $client->getResponse();
            static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
            $response = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertEquals('FRAMEWORK__MISSING_PRIVILEGE_ERROR', $response['errors'][0]['code'] ?? null);
            static::assertStringContainsString('product:read', $response['errors'][0]['detail']);
            static::assertStringContainsString('tax:read', $response['errors'][0]['detail']);
            static::assertStringContainsString('product_category:read', $response['errors'][0]['detail']);
            static::assertStringContainsString('media:read', $response['errors'][0]['detail']);

            $client = $this->getBrowser();
            TestUser::createNewTestUser(
                $client->getContainer()->get(Connection::class),
                ['import_export_file:create', 'product:read', 'tax:read', 'product_category:read', 'product_media:read', 'media:read']
            )->authorizeBrowser($client);
            $client->request(
                'POST',
                '/api/_action/import-export/prepare',
                ['profileId' => $entry['id'], 'expireDate' => date('Y-m-d H:i:s')],
                [],
                ['Content-Type' => 'multipart/formdata']
            );

            $response = $client->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        }
    }

    public function testStartingDryRunImport(): void
    {
        $data = $this->prepareImportExportActionControllerTestData(1);

        $this->repository->create(array_values($data), $this->context);

        foreach ($data as $entry) {
            $client = $this->getBrowser();
            $client->request(
                'POST',
                '/api/_action/import-export/prepare',
                ['profileId' => $entry['id'], 'expireDate' => date('Y-m-d H:i:s'), 'dryRun' => true],
                ['file' => $this->getUploadFile($entry['fileType'])],
                ['Content-Type' => 'multipart/formdata']
            );

            $response = $client->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());

            $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame(ImportExportLogEntity::ACTIVITY_DRYRUN, $content['log']['activity']);
        }
    }

    /**
     * @dataProvider mappingFromProvider
     */
    public function testMappingFromTemplate(string $sourceEntity, string $fileContent, array $expectedMapping, ?int $expectedErrorCode = null, ?string $expectedErrorMessage = null): void
    {
        $file = [];
        if ($fileContent !== '') {
            $file = ['file' => $this->getUploadFile('text/csv', '', $fileContent)];
        }

        $parameters = [];
        if ($sourceEntity !== '') {
            $parameters = ['sourceEntity' => $sourceEntity];
        }

        $client = $this->getBrowser();
        $client->request(
            'POST',
            '/api/_action/import-export/mapping-from-template',
            $parameters,
            $file,
            ['Content-Type' => 'multipart/formdata']
        );

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if ($expectedErrorCode !== null) {
            static::assertSame($expectedErrorCode, $response->getStatusCode());
        }

        if ($expectedErrorMessage !== null) {
            static::assertSame($expectedErrorMessage, $content['errors'][0]['detail']);
        }

        if ($expectedErrorCode !== null || $expectedErrorMessage !== null) {
            return;
        }

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $result = array_map(fn ($mapping) => ['key' => $mapping['key'], 'mappedKey' => $mapping['mappedKey']], $content);
        static::assertSame($expectedMapping, $result);
    }

    public static function mappingFromProvider(): iterable
    {
        yield 'Product entity with mapped keys' => [
            'sourceEntity' => 'product',
            'fileContent' => 'id;productNumber;foo;bar;manufacturer_id',
            'expectedMapping' => [
                ['key' => 'id', 'mappedKey' => 'id'],
                ['key' => 'productNumber', 'mappedKey' => 'productNumber'],
                ['key' => '', 'mappedKey' => 'foo'],
                ['key' => '', 'mappedKey' => 'bar'],
                ['key' => 'manufacturer.id', 'mappedKey' => 'manufacturer_id'],
            ],
        ];

        yield 'Product entity without mapped keys' => [
            'sourceEntity' => 'product',
            'fileContent' => 'test;noValid;foo;bar;lastOne',
            'expectedMapping' => [
                ['key' => '', 'mappedKey' => 'test'],
                ['key' => '', 'mappedKey' => 'noValid'],
                ['key' => '', 'mappedKey' => 'foo'],
                ['key' => '', 'mappedKey' => 'bar'],
                ['key' => '', 'mappedKey' => 'lastOne'],
            ],
        ];

        yield 'Not existing source entity' => [
            'sourceEntity' => 'invalid-entity',
            'fileContent' => 'test;noValid;foo;bar;lastOne',
            'expectedMapping' => [],
            'expectedErrorCode' => 500,
            'expectedErrorMessage' => 'Definition for entity "invalid-entity" does not exist.',
        ];

        yield 'Invalid source entity parameter' => [
            'sourceEntity' => '',
            'fileContent' => 'test;noValid;foo;bar;lastOne',
            'expectedMapping' => [],
            'expectedErrorCode' => 400,
            'expectedErrorMessage' => 'The parameter "sourceEntity" is invalid.',
        ];

        yield 'Invalid file parameter' => [
            'sourceEntity' => 'product',
            'fileContent' => '',
            'expectedMapping' => [],
            'expectedErrorCode' => 400,
            'expectedErrorMessage' => 'The parameter "file" is invalid.',
        ];
    }

    /**
     * Prepare a defined number of test data.
     */
    protected function prepareImportExportActionControllerTestData(int $num = 1): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $mimetypes = $this->getValidMimeTypes();

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'name' => 'Foobar' . $i,
                'label' => 'Foobar' . $i,
                'systemDefault' => ($i % 2 === 0),
                'sourceEntity' => 'product',
                'fileType' => $mimetypes[$i % \count($mimetypes)],
                'delimiter' => ';',
                'enclosure' => '"',
                'createdAt' => date('Y-m-d H:i:s'),
                'mapping' => [
                    [
                        'key' => 'id',
                        'mappedKey' => 'id',
                        'position' => 0,
                    ],
                    [
                        'key' => 'translations.DEFAULT.name',
                        'mappedKey' => 'name',
                        'position' => 1,
                    ],
                    [
                        'key' => 'price.DEFAULT.net',
                        'mappedKey' => 'price_net',
                        'position' => 2,
                    ],
                    [
                        'key' => 'tax.id',
                        'mappedKey' => 'tax_id',
                        'position' => 3,
                    ],
                    [
                        'key' => 'categories',
                        'mappedKey' => 'categories',
                        'position' => 4,
                    ],
                    [
                        'key' => 'cover.media.translations.DEFAULT.title',
                        'mappedKey' => 'cover_media_title',
                        'position' => 5,
                    ],
                ],
            ];
        }

        return $data;
    }

    protected function getValidMimeTypes(): array
    {
        return ['text/csv'];
    }

    private function getUploadFile(string $type = 'text/csv', string $forceFileName = '', ?string $content = null): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'upl');
        static::assertIsString($file);

        switch ($type) {
            case 'text/html':
                $content ??= '<!DOCTYPE html><html><body></body></html>';
                $fileName = 'test.html';

                break;
            case 'text/xml':
                $content ??= '<?xml version="1.0" ?><foo></foo>';
                $fileName = 'test.xml';

                break;
            case 'text/csv':
            default:
                $content ??= '"foo";"bar";"123"';
                $fileName = 'test.csv';
        }
        file_put_contents($file, $content);

        if (!empty($forceFileName)) {
            $fileName = $forceFileName;
        }

        return new UploadedFile($file, $fileName, $type);
    }
}
