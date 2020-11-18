<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ImportExportActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Context
     */
    private $context;

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
                '/api/v' . PlatformRequest::API_VERSION . '/_action/import-export/prepare',
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
                '/api/v' . PlatformRequest::API_VERSION . '/_action/import-export/prepare',
                ['profileId' => $entry['id'], 'expireDate' => date('Y-m-d H:i:s')],
                ['file' => $this->getUploadFile($entry['fileType'])],
                ['Content-Type' => 'multipart/formdata']
            );

            $response = $client->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        }
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
                'sourceEntity' => 'article' . $i,
                'fileType' => $mimetypes[$i % count($mimetypes)],
                'delimiter' => ';',
                'enclosure' => '"',
                'createdAt' => date('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    protected function getValidMimeTypes(): array
    {
        return ['text/csv'];
    }

    private function getUploadFile(string $type = 'text/csv', string $forceFileName = ''): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'upl');

        switch ($type) {
            case 'text/html':
                $content = '<!DOCTYPE html><html><body></body></html>';
                $fileName = 'test.html';

                break;
            case 'text/xml':
                $content = '<?xml version="1.0" ?><foo></foo>';
                $fileName = 'test.xml';

                break;
            case 'text/csv':
            default:
                $content = '"foo";"bar";"123"';
                $fileName = 'test.csv';
        }
        file_put_contents($file, $content);

        if (!empty($forceFileName)) {
            $fileName = $forceFileName;
        }

        return new UploadedFile($file, $fileName, $type);
    }
}
