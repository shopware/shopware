<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class ImportExportFileApiTest extends TestCase
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
        $this->repository = $this->getContainer()->get('import_export_file.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testImportExportFileCreateSuccess(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);

        foreach ($data as $entry) {
            $this->getBrowser()->request('POST', $this->prepareRoute(), [], [], [], json_encode($entry));
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        }
        $records = $this->connection->fetchAll('SELECT * FROM import_export_file');

        static::assertCount($num, $records);
        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertSame($expect['originalName'], $record['original_name']);
            static::assertSame($expect['path'], $record['path']);
            static::assertEquals(strtotime($expect['expireDate']), strtotime($record['expire_date']));
            static::assertEquals($expect['size'], $record['size']);
            static::assertSame($expect['accessToken'], $record['access_token']);
            unset($data[$record['id']]);
        }
    }

    public function testImportExportFileCreateMissingRequired(): void
    {
        $requiredProperties = ['originalName', 'path'];
        foreach ($requiredProperties as $property) {
            $entry = current($this->prepareImportExportFileTestData());
            unset($entry[$property]);
            $this->getBrowser()->request('POST', $this->prepareRoute(), $entry);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $response->getContent());
        }
    }

    public function testImportExportFileList(): void
    {
        foreach ([0, 5] as $num) {
            $data = $this->prepareImportExportFileTestData($num);
            if (!empty($data)) {
                $this->repository->create(array_values($data), $this->context);
            }

            $this->getBrowser()->request('GET', $this->prepareRoute(), [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());

            $content = json_decode($response->getContent());

            $expectData = [];
            foreach (array_values($data) as $entry) {
                $expectData[$entry['id']] = $entry;
            }

            static::assertEquals($num, $content->total);
            for ($i = 0; $i < $num; ++$i) {
                $importExportFile = $content->data[$i];
                $expect = $expectData[$importExportFile->_uniqueIdentifier];
                static::assertSame($expect['originalName'], $importExportFile->originalName);
                static::assertSame($expect['path'], $importExportFile->path);
                static::assertEquals(strtotime($expect['expireDate']), strtotime($importExportFile->expireDate));
                static::assertEquals($expect['size'], $importExportFile->size);
                static::assertSame($expect['accessToken'], $importExportFile->accessToken);
            }
        }
    }

    public function testImportExportFileUpdateFull(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $ids = array_column($data, 'id');
        $data = $this->rotateTestdata($data);

        $expectData = [];
        foreach ($ids as $idx => $id) {
            $expectData[$id] = $data[$idx];
            unset($data[$idx]['id']);

            $this->getBrowser()->request('PATCH', $this->prepareRoute() . $id, [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ], json_encode($data[$idx]));
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        }

        $this->getBrowser()->request('GET', $this->prepareRoute(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent());

        static::assertEquals($num, $content->total);
        for ($i = 0; $i < $num; ++$i) {
            $importExportFile = $content->data[$i];
            $expect = $expectData[$importExportFile->_uniqueIdentifier];
            static::assertSame($expect['originalName'], $importExportFile->originalName);
            static::assertSame($expect['path'], $importExportFile->path);
            static::assertEquals(strtotime($expect['expireDate']), strtotime($importExportFile->expireDate));
            static::assertEquals($expect['size'], $importExportFile->size);
            static::assertSame($expect['accessToken'], $importExportFile->accessToken);
        }
    }

    public function testImportExportFileUpdateSuccessPartial(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $ids = array_column($data, 'id');
        $data = $this->rotateTestdata($data);

        $properties = array_keys(current($data));
        $expectProperties = $properties;

        $expectData = [];
        foreach ($ids as $idx => $id) {
            $removedProperty = array_pop($properties);
            $expectData[$id] = $data[$idx];
            unset($data[$idx][$removedProperty]);
            unset($data[$idx]['id']);

            $this->getBrowser()->request('PATCH', $this->prepareRoute() . $id, [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ], json_encode($data[$idx]));
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

            $this->getBrowser()->request('GET', $this->prepareRoute() . $id, [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

            $content = json_decode($response->getContent(), true);

            $importExportFile = $content['data'];
            $expect = $expectData[$id];
            foreach ($expectProperties as $property) {
                if ($property === 'id') {
                    continue;
                }
                $currentValue = $importExportFile[$property];
                $expectValue = $expect[$property];
                if ($property === 'expireDate') {
                    $currentValue = strtotime($currentValue);
                    $expectValue = strtotime($expectValue);
                }
                if ($property === $removedProperty) {
                    static::assertNotEquals($expectValue, $currentValue);
                } else {
                    static::assertEquals($expectValue, $currentValue);
                }
            }
        }
    }

    public function testImportExportFileDetailSuccess(): void
    {
        $num = 2;
        $data = $this->prepareImportExportFileTestData($num);
        $this->repository->create(array_values($data), $this->context);

        foreach (array_values($data) as $expect) {
            $this->getBrowser()->request('GET', $this->prepareRoute() . $expect['id'], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

            $content = json_decode($response->getContent());
            static::assertSame($expect['originalName'], $content->data->originalName);
            static::assertSame($expect['path'], $content->data->path);
            static::assertEquals(strtotime($expect['expireDate']), strtotime($content->data->expireDate));
            static::assertEquals($expect['size'], $content->data->size);
            static::assertSame($expect['accessToken'], $content->data->accessToken);
        }
    }

    public function testImportExportFileDetailNotFound(): void
    {
        $this->getBrowser()->request('GET', $this->prepareRoute() . Uuid::randomHex(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testImportExportFileSearch(): void
    {
        $data = $this->prepareImportExportFileTestData(2);

        $invalidData = array_pop($data);

        $this->repository->create(array_values($data), $this->context);
        $searchData = array_pop($data);

        $filter = [];
        foreach ($searchData as $key => $value) {
            $filter['filter'][$key] = $invalidData[$key];
            $this->getBrowser()->request('POST', $this->prepareRoute(true), $filter, [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
            $content = json_decode($response->getContent());
            static ::assertEquals(0, $content->total);

            $filter['filter'][$key] = $value;
            $this->getBrowser()->request('POST', $this->prepareRoute(true), $filter, [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
            $content = json_decode($response->getContent());
            static ::assertEquals(1, $content->total);
        }
    }

    public function testImportExportFileDelete(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);
        $this->repository->create(array_values($data), $this->context);
        $deleteId = array_column($data, 'id')[0];

        $this->getBrowser()->request('DELETE', $this->prepareRoute() . Uuid::randomHex(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $records = $this->connection->fetchAll('SELECT * FROM import_export_file');
        static::assertEquals($num, \count($records));

        $this->getBrowser()->request('DELETE', $this->prepareRoute() . $deleteId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $records = $this->connection->fetchAll('SELECT * FROM import_export_file');
        static::assertEquals($num - 1, \count($records));
    }

    protected function prepareRoute(bool $search = false): string
    {
        $addPath = '';
        if ($search) {
            $addPath = '/search';
        }

        return '/api' . $addPath . '/import-export-file/';
    }

    /**
     * Prepare a defined number of test data.
     */
    protected function prepareImportExportFileTestData(int $num = 1, string $add = ''): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'originalName' => sprintf('file%d.xml', $i),
                'path' => sprintf('/test/%d/%s', $i, $add),
                'expireDate' => sprintf('2011-01-01T15:03:%02d', $i),
                'size' => $i * 51,
                'accessToken' => Random::getBase64UrlString(32),
            ];
        }

        return $data;
    }

    protected function rotateTestdata(array $data): array
    {
        array_push($data, array_shift($data));

        return array_values($data);
    }
}
