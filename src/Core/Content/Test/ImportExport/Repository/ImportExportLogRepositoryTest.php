<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @internal
 */
#[Package('system-settings')]
class ImportExportLogRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $logRepository;

    private EntityRepository $profileRepository;

    private EntityRepository $fileRepository;

    private EntityRepository $userRepository;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->logRepository = $this->getContainer()->get('import_export_log.repository');
        $this->profileRepository = $this->getContainer()->get('import_export_profile.repository');
        $this->fileRepository = $this->getContainer()->get('import_export_file.repository');
        $this->userRepository = $this->getContainer()->get('user.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testImportExportLogSingleCreateSuccess(): void
    {
        $data = $this->prepareImportExportLogTestData();

        $id = array_key_first($data);

        $this->logRepository->create([$data[$id]], $this->context);

        $record = $this->connection->fetchAssociative('SELECT * FROM import_export_log WHERE id = :id', ['id' => $id]);
        static::assertIsArray($record);

        $expect = $data[$id];
        static::assertNotEmpty($record);
        static::assertEquals($id, $record['id']);
        static::assertSame($expect['activity'], $record['activity']);
        static::assertSame($expect['state'], $record['state']);
        static::assertSame($expect['userId'], Uuid::fromBytesToHex($record['user_id']));
        static::assertSame($expect['profileId'], Uuid::fromBytesToHex($record['profile_id']));
        static::assertSame($expect['fileId'], Uuid::fromBytesToHex($record['file_id']));
        static::assertSame($expect['username'], $record['username']);
        static::assertSame($expect['profileName'], $record['profile_name']);
    }

    public function testImportExportLogSingleCreateWrongScope(): void
    {
        $data = $this->prepareImportExportLogTestData();

        try {
            $this->context->scope(Context::USER_SCOPE, function (Context $context) use ($data): void {
                $this->logRepository->create(array_values($data), $context);
            });
            static::fail(sprintf('Create within wrong scope \'%s\'', Context::USER_SCOPE));
        } catch (\Exception $e) {
            static::assertInstanceOf(AccessDeniedHttpException::class, $e);
        }
    }

    public function testImportExportLogSingleCreateMissingRequired(): void
    {
        $requiredProperties = ['activity', 'state', 'records'];
        $num = \count($requiredProperties);
        $data = $this->prepareImportExportLogTestData($num);

        foreach ($requiredProperties as $property) {
            $entry = array_shift($data);
            unset($entry[$property]);

            try {
                static::assertNotNull($entry);
                $this->logRepository->create([$entry], $this->context);
                static::fail(sprintf('Create without required property \'%s\'', $property));
            } catch (\Exception $e) {
                static::assertInstanceOf(WriteException::class, $e);
                static::assertInstanceOf(WriteConstraintViolationException::class, $e->getExceptions()[0]);
            }
        }
    }

    public function testImportExportLogMultiCreateSuccess(): void
    {
        $num = 5;
        $data = $this->prepareImportExportLogTestData($num);

        $this->logRepository->create(array_values($data), $this->context);

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_log');

        static::assertCount($num, $records);

        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertSame($expect['activity'], $record['activity']);
            static::assertSame($expect['state'], $record['state']);
            static::assertSame($expect['userId'], Uuid::fromBytesToHex($record['user_id']));
            static::assertSame($expect['profileId'], Uuid::fromBytesToHex($record['profile_id']));
            static::assertSame($expect['fileId'], Uuid::fromBytesToHex($record['file_id']));
            static::assertSame($expect['username'], $record['username']);
            static::assertSame($expect['profileName'], $record['profile_name']);
            unset($data[$record['id']]);
        }
    }

    public function testImportExportLogMultiCreateMissingRequired(): void
    {
        $data = $this->prepareImportExportLogTestData(2);

        $requiredProperties = ['activity', 'state', 'records'];
        $incompleteData = $this->prepareImportExportLogTestData(\count($requiredProperties));

        foreach ($requiredProperties as $property) {
            $entry = array_shift($incompleteData);
            unset($entry[$property]);
            static::assertNotNull($entry);
            array_push($data, $entry);
        }

        try {
            static::assertNotNull($data);
            $this->logRepository->create(array_values($data), $this->context);
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

            static::assertEquals($missingPropertyPaths, $foundViolations);
        }
    }

    public function testImportExportLogReadSuccess(): void
    {
        $num = 3;
        $data = $this->prepareImportExportLogTestData($num);

        $this->logRepository->create(array_values($data), $this->context);

        foreach ($data as $expect) {
            $id = $expect['id'];
            $result = $this->logRepository->search(new Criteria([$id]), $this->context);
            /** @var ImportExportLogEntity $ImportExportLog */
            $ImportExportLog = $result->get($id);
            static::assertCount(1, $result);
            static::assertSame($expect['activity'], $ImportExportLog->getActivity());
            static::assertSame($expect['state'], $ImportExportLog->getState());
            static::assertSame($expect['userId'], $ImportExportLog->getUserId());
            static::assertSame($expect['profileId'], $ImportExportLog->getProfileId());
            static::assertSame($expect['fileId'], $ImportExportLog->getFileId());
            static::assertSame($expect['username'], $ImportExportLog->getUsername());
            static::assertSame($expect['profileName'], $ImportExportLog->getProfileName());
        }
    }

    public function testImportExportLogReadNoResult(): void
    {
        $num = 3;
        $data = $this->prepareImportExportLogTestData($num);

        $this->logRepository->create(array_values($data), $this->context);

        $result = $this->logRepository->search(new Criteria([Uuid::randomHex()]), $this->context);
        static::assertCount(0, $result);
    }

    public function testImportExportLogUpdateFull(): void
    {
        $num = 3;
        $origDate = $data = $this->prepareImportExportLogTestData($num);

        $this->logRepository->create(array_values($data), $this->context);

        $new_data = array_values($this->prepareImportExportLogTestData($num, 'xxx'));
        foreach ($data as $id => $value) {
            $new_value = array_pop($new_data);
            $new_value['id'] = $value['id'];
            $data[$id] = $new_value;
        }

        $this->logRepository->upsert(array_values($data), $this->context);

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_log');

        static::assertCount($num, $records);

        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertSame($expect['activity'], $record['activity']);
            static::assertSame($expect['state'], $record['state']);
            static::assertSame($expect['userId'], Uuid::fromBytesToHex($record['user_id']));
            static::assertSame($expect['profileId'], Uuid::fromBytesToHex($record['profile_id']));
            static::assertSame($expect['fileId'], Uuid::fromBytesToHex($record['file_id']));
            static::assertSame($expect['username'], $record['username']);
            static::assertSame($expect['profileName'], $record['profile_name']);
            unset($data[$record['id']]);
        }

        // Verify update only in system scope.
        try {
            $this->context->scope(Context::USER_SCOPE, function (Context $context) use ($origDate): void {
                $this->logRepository->upsert(array_values($origDate), $context);
            });
            static::fail(sprintf('Update within wrong scope \'%s\'', Context::USER_SCOPE));
        } catch (\Exception $e) {
            static::assertInstanceOf(AccessDeniedHttpException::class, $e);
        }
    }

    public function testImportExportLogUpdatePartial(): void
    {
        $upsertData = [];
        $origDate = $data = $this->prepareImportExportLogTestData();
        $item = array_pop($data);
        static::assertNotNull($item);
        $properties = array_keys($item);

        $num = \count($properties);
        $data = $this->prepareImportExportLogTestData($num, 'x');

        $this->logRepository->create(array_values($data), $this->context);

        $new_data = array_values($this->prepareImportExportLogTestData($num, 'xxx'));
        $upsertData = [];
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

        static::assertNotEmpty($upsertData);
        $this->logRepository->upsert(array_values($upsertData), $this->context);

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_log');

        static::assertCount($num, $records);

        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertSame($expect['activity'], $record['activity']);
            static::assertSame($expect['state'], $record['state']);
            static::assertSame($expect['userId'], Uuid::fromBytesToHex($record['user_id']));
            static::assertSame($expect['profileId'], Uuid::fromBytesToHex($record['profile_id']));
            static::assertSame($expect['fileId'], Uuid::fromBytesToHex($record['file_id']));
            static::assertSame($expect['username'], $record['username']);
            static::assertSame($expect['profileName'], $record['profile_name']);
            unset($data[$record['id']]);
        }

        // Verify update only in system scope.
        try {
            $this->context->scope(Context::USER_SCOPE, function (Context $context) use ($origDate): void {
                $this->logRepository->upsert(array_values($origDate), $context);
            });
            static::fail(sprintf('Update within wrong scope \'%s\'', Context::USER_SCOPE));
        } catch (\Exception $e) {
            static::assertInstanceOf(AccessDeniedHttpException::class, $e);
        }
    }

    public function testImportExportLogDeleteSuccess(): void
    {
        $num = 10;
        $data = $this->prepareImportExportLogTestData($num);
        $this->logRepository->create(array_values($data), $this->context);

        $ids = [];
        foreach (array_column($data, 'id') as $id) {
            $ids[] = ['id' => $id];
        }

        $this->logRepository->delete($ids, $this->context);

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_log');

        static::assertCount(0, $records);
    }

    public function testImportExportLogDeleteUnknown(): void
    {
        $num = 10;
        $data = $this->prepareImportExportLogTestData($num);
        $this->logRepository->create(array_values($data), $this->context);

        $ids = [];
        for ($i = 0; $i <= $num; ++$i) {
            $ids[] = ['id' => Uuid::randomHex()];
        }

        $this->logRepository->delete($ids, $this->context);

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_log');

        static::assertCount($num, $records);
    }

    public function testImportExportLogDeleteWrongScope(): void
    {
        $num = 10;
        $data = $this->prepareImportExportLogTestData($num);
        $this->logRepository->create(array_values($data), $this->context);

        $ids = [];
        foreach (array_column($data, 'id') as $id) {
            $ids[] = ['id' => $id];
        }

        try {
            $this->context->scope(Context::USER_SCOPE, function (Context $context) use ($ids): void {
                $this->logRepository->delete($ids, $context);
            });
            static::fail(sprintf('Delete within wrong scope \'%s\'', Context::USER_SCOPE));
        } catch (\Exception $e) {
            static::assertInstanceOf(AccessDeniedHttpException::class, $e);
        }

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_log');

        static::assertCount($num, $records);
    }

    /**
     * Prepare a defined number of test data.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function prepareImportExportLogTestData(int $num = 1, string $add = ''): array
    {
        $data = [];
        $users = [];
        $userIds = [];
        $fileIds = [];
        $profiles = [];
        $profileIds = [];
        $activities = [];

        if ($num > 0) {
            // Dependencies
            $users = $this->prepareUsers(2);
            $userIds = array_column($users, 'id');
            $files = $this->prepareFiles(2);
            $fileIds = array_column($files, 'id');
            $profiles = $this->prepareProfiles(2);
            $profileIds = array_column($profiles, 'id');
            $activities = ['import', 'export'];
        }

        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $profile = $profiles[Uuid::fromHexToBytes($profileIds[$i % 2])];

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'activity' => ($activities[$i % 2] ?? '') . $add,
                'state' => sprintf('state %d', $i),
                'userId' => $userIds[$i % 2],
                'profileId' => $profileIds[$i % 2],
                'fileId' => $fileIds[$i % 2],
                'username' => $users[Uuid::fromHexToBytes($userIds[$i % 2])]['username'] . $add,
                'profileName' => $profile['label'] . $add,
                'records' => 10 * $i,
                'config' => ['profile' => $profile],
            ];
        }

        return $data;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function prepareUsers(int $num = 1): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => sprintf('user_%s', Uuid::randomHex()),
                'password' => sprintf('shopwarepw%d', $i),
                'firstName' => sprintf('Foo%d', $i),
                'lastName' => sprintf('Bar%d', $i),
                'email' => sprintf('%s@foo.bar', $uuid),
            ];
        }
        $this->userRepository->create(array_values($data), $this->context);

        return $data;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function prepareFiles(int $num = 1): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'originalName' => sprintf('file%d.xml', $i),
                'path' => sprintf('/test/test%d', $i),
                'expireDate' => sprintf('2011-01-01T15:03:%02d', $i),
                'accessToken' => Random::getBase64UrlString(32),
            ];
        }
        $this->fileRepository->create(array_values($data), $this->context);

        return $data;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function prepareProfiles(int $num = 1): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'name' => sprintf('Test name %d', $i),
                'label' => sprintf('Test label %d', $i),
                'systemDefault' => ($i % 2 === 0),
                'sourceEntity' => sprintf('Test entity %d', $i),
                'fileType' => sprintf('Test file type %d', $i),
                'delimiter' => sprintf('Test delimiter %d', $i),
                'enclosure' => sprintf('Test enclosure %d', $i),
                'mapping' => ['Mapping ' . $i => 'Value ' . $i],
            ];
        }
        $this->profileRepository->create(array_values($data), $this->context);

        return $data;
    }
}
