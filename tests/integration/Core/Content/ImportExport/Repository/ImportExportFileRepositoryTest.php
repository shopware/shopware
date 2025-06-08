<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportFileRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $repository;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('import_export_file.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testImportExportFileSingleCreateSuccess(): void
    {
        $data = $this->prepareImportExportFileTestData();

        $id = array_key_first($data);

        $this->repository->create([$data[$id]], $this->context);

        $record = $this->connection->fetchAssociative('SELECT * FROM import_export_file WHERE id = :id', ['id' => $id]);

        $expect = $data[$id];
        static::assertNotEmpty($record);
        static::assertSame($id, $record['id']);
        static::assertSame($expect['originalName'], $record['original_name']);
        static::assertSame($expect['path'], $record['path']);
        static::assertSame(strtotime((string) $expect['expireDate']), strtotime((string) $record['expire_date']));
        static::assertSame($expect['size'], (int) $record['size']);
        static::assertSame($expect['accessToken'], $record['access_token']);
    }

    public function testImportExportFileSingleCreateMissingRequired(): void
    {
        $requiredProperties = ['originalName', 'path'];
        $num = \count($requiredProperties);
        $data = $this->prepareImportExportFileTestData($num);

        foreach ($requiredProperties as $property) {
            $entry = array_shift($data);
            unset($entry[$property]);

            try {
                $this->repository->create([$entry], $this->context);
                static::fail(\sprintf('Create without required property \'%s\'', $property));
            } catch (\Exception $e) {
                static::assertInstanceOf(WriteException::class, $e);
            }
        }
    }

    public function testImportExportFileMultiCreateSuccess(): void
    {
        $num = 5;
        $data = $this->prepareImportExportFileTestData($num);

        $this->repository->create(array_values($data), $this->context);

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_file');

        static::assertCount($num, $records);

        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertSame($expect['originalName'], $record['original_name']);
            static::assertSame($expect['path'], $record['path']);
            static::assertSame(strtotime((string) $expect['expireDate']), strtotime((string) $record['expire_date']));
            static::assertSame($expect['size'], (int) $record['size']);
            static::assertSame($expect['accessToken'], $record['access_token']);
            unset($data[$record['id']]);
        }
    }

    public function testImportExportFileMultiCreateMissingRequired(): void
    {
        $data = $this->prepareImportExportFileTestData(2);

        $requiredProperties = ['originalName', 'path'];
        $incompleteData = $this->prepareImportExportFileTestData(\count($requiredProperties));

        foreach ($requiredProperties as $property) {
            $entry = array_shift($incompleteData);
            unset($entry[$property]);
            $data[] = $entry;
        }

        try {
            $this->repository->create(array_values($data), $this->context);
            static::fail('Create without required properties');
        } catch (WriteException $e) {
            static::assertCount(\count($requiredProperties), $e->getExceptions());
            $foundViolations = [];

            /** @var WriteConstraintViolationException $violations */
            foreach ($e->getExceptions() as $violations) {
                foreach ($violations->getViolations() as $violation) {
                    $foundViolations[] = $violation->getPropertyPath();
                }
            }

            $missingPropertyPaths = array_map(fn ($property) => '/' . $property, $requiredProperties);

            static::assertSame($missingPropertyPaths, $foundViolations);
        }
    }

    public function testImportExportFileReadSuccess(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);

        $this->repository->create(array_values($data), $this->context);

        foreach ($data as $expect) {
            $id = $expect['id'];
            $result = $this->repository->search(new Criteria([$id]), $this->context);
            $importExportFile = $result->get($id);
            static::assertInstanceOf(ImportExportFileEntity::class, $importExportFile);
            static::assertCount(1, $result);
            static::assertSame($expect['originalName'], $importExportFile->getOriginalName());
            static::assertSame($expect['path'], $importExportFile->getPath());
            static::assertSame((new \DateTime($expect['expireDate']))->format(Defaults::STORAGE_DATE_TIME_FORMAT), $importExportFile->getExpireDate()->format(Defaults::STORAGE_DATE_TIME_FORMAT));
            static::assertSame($expect['size'], $importExportFile->getSize());
            static::assertSame($expect['accessToken'], $importExportFile->getAccessToken());
        }
    }

    public function testImportExportFileReadNoResult(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);

        $this->repository->create(array_values($data), $this->context);

        $result = $this->repository->search(new Criteria([Uuid::randomHex()]), $this->context);
        static::assertCount(0, $result);
    }

    public function testImportExportFileUpdateFull(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);

        $this->repository->create(array_values($data), $this->context);

        $new_data = array_values($this->prepareImportExportFileTestData($num, 'xxx'));
        foreach ($data as $id => $value) {
            $new_value = array_pop($new_data);
            $new_value['id'] = $value['id'];
            $data[$id] = $new_value;
        }

        $this->repository->upsert(array_values($data), $this->context);

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_file');

        static::assertCount($num, $records);

        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertSame($expect['originalName'], $record['original_name']);
            static::assertSame($expect['path'], $record['path']);
            static::assertSame(strtotime((string) $expect['expireDate']), strtotime((string) $record['expire_date']));
            static::assertSame($expect['size'], (int) $record['size']);
            static::assertSame($expect['accessToken'], $record['access_token']);
            unset($data[$record['id']]);
        }
    }

    public function testImportExportFileUpdatePartial(): void
    {
        $upsertData = [];
        $data = $this->prepareImportExportFileTestData();
        $properties = array_keys(array_pop($data));

        $num = \count($properties);
        $data = $this->prepareImportExportFileTestData($num);

        $this->repository->create(array_values($data), $this->context);

        $new_data = array_values($this->prepareImportExportFileTestData($num, 'xxx'));
        foreach ($data as $id => $value) {
            $new_value = array_pop($new_data);
            $new_value['id'] = $value['id'];
            $data[$id] = $new_value;
            $upsertData = $data;

            // Remove property before write
            $property = array_pop($properties);
            if ($property === 'id') {
                continue;
            }
            unset($upsertData[$id][$property]);
        }

        $this->repository->upsert(array_values($upsertData), $this->context);

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_file');

        static::assertCount($num, $records);

        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertSame($expect['originalName'], $record['original_name']);
            static::assertSame($expect['path'], $record['path']);
            static::assertSame(strtotime((string) $expect['expireDate']), strtotime((string) $record['expire_date']));
            static::assertSame($expect['size'], (int) $record['size']);
            static::assertSame($expect['accessToken'], $record['access_token']);
            unset($data[$record['id']]);
        }
    }

    public function testImportExportFileDeleteSuccess(): void
    {
        $num = 10;
        $data = $this->prepareImportExportFileTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $ids = [];
        foreach (array_column($data, 'id') as $id) {
            $ids[] = ['id' => $id];
        }

        $this->repository->delete($ids, $this->context);

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_file');

        static::assertCount(0, $records);
    }

    public function testImportExportFileDeleteUnknown(): void
    {
        $num = 10;
        $data = $this->prepareImportExportFileTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $ids = [];
        for ($i = 0; $i <= $num; ++$i) {
            $ids[] = ['id' => Uuid::randomHex()];
        }

        $this->repository->delete($ids, $this->context);

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_file');

        static::assertCount($num, $records);
    }

    /**
     * Prepare a defined number of test data.
     *
     * @return array<string, mixed>
     */
    protected function prepareImportExportFileTestData(int $num = 1, string $add = ''): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'originalName' => \sprintf('file%d.xml', $i),
                'path' => \sprintf('/test/%d/%s', $i, $add),
                'expireDate' => \sprintf('2011-01-01T15:03:%02d', $i),
                'size' => $i * 51,
                'accessToken' => Random::getBase64UrlString(32),
            ];
        }

        return $data;
    }
}
