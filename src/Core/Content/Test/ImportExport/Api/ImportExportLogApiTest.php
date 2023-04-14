<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('system-settings')]
class ImportExportLogApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

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

    public function testImportExportLogCreateFailedWriteProtected(): void
    {
        $num = 3;
        $data = $this->prepareImportExportLogTestData($num);

        foreach ($data as $entry) {
            $this->getBrowser()->request('POST', $this->prepareRoute(), $entry);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        }
    }

    public function testImportExportLogList(): void
    {
        foreach ([0, 5] as $num) {
            $data = $this->prepareImportExportLogTestData($num);
            if (!empty($data)) {
                $this->logRepository->create(array_values($data), $this->context);
            }

            $this->getBrowser()->request('GET', $this->prepareRoute(), [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

            $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            $expectData = [];
            foreach (array_values($data) as $entry) {
                $expectData[$entry['id']] = $entry;
            }

            static::assertEquals($num, $content['total']);
            for ($i = 0; $i < $num; ++$i) {
                $importExportLog = $content['data'][$i];
                $expect = $expectData[$importExportLog['_uniqueIdentifier']];
                static::assertEquals($expect['activity'], $importExportLog['activity']);
                static::assertEquals($expect['state'], $importExportLog['state']);
                static::assertEquals($expect['userId'], $importExportLog['userId']);
                static::assertEquals($expect['profileId'], $importExportLog['profileId']);
                static::assertEquals($expect['fileId'], $importExportLog['fileId']);
                static::assertEquals($expect['username'], $importExportLog['username']);
                static::assertEquals($expect['profileName'], $importExportLog['profileName']);
            }
        }
    }

    public function testImportExportLogUpdateFailedWriteProtected(): void
    {
        $num = 3;
        $data = $this->prepareImportExportLogTestData($num);
        $this->logRepository->create(array_values($data), $this->context);

        $ids = array_column($data, 'id');
        $updateData = $this->rotateTestdata($data);

        $expectData = [];
        foreach ($ids as $idx => $id) {
            $expectData[$id] = array_values($data)[$idx];
            unset($updateData[$idx]['id']);

            $this->getBrowser()->request('PATCH', $this->prepareRoute() . $id, $updateData[$idx], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        }

        $this->getBrowser()->request('GET', $this->prepareRoute(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals($num, $content['total']);
        for ($i = 0; $i < $num; ++$i) {
            $importExportLog = $content['data'][$i];
            $expect = $expectData[$importExportLog['_uniqueIdentifier']];
            static::assertEquals($expect['activity'], $importExportLog['activity']);
            static::assertEquals($expect['state'], $importExportLog['state']);
            static::assertEquals($expect['userId'], $importExportLog['userId']);
            static::assertEquals($expect['profileId'], $importExportLog['profileId']);
            static::assertEquals($expect['fileId'], $importExportLog['fileId']);
            static::assertEquals($expect['username'], $importExportLog['username']);
            static::assertEquals($expect['profileName'], $importExportLog['profileName']);
        }
    }

    public function testImportExportLogDetailSuccess(): void
    {
        $num = 2;
        $data = $this->prepareImportExportLogTestData($num);
        $this->logRepository->create(array_values($data), $this->context);

        foreach (array_values($data) as $expect) {
            $this->getBrowser()->request('GET', $this->prepareRoute() . $expect['id'], [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

            $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertEquals($expect['activity'], $content['data']['activity']);
            static::assertEquals($expect['state'], $content['data']['state']);
            static::assertEquals($expect['userId'], $content['data']['userId']);
            static::assertEquals($expect['profileId'], $content['data']['profileId']);
            static::assertEquals($expect['fileId'], $content['data']['fileId']);
            static::assertEquals($expect['username'], $content['data']['username']);
            static::assertEquals($expect['profileName'], $content['data']['profileName']);
        }
    }

    public function testImportExportLogDetailNotFound(): void
    {
        $this->getBrowser()->request('GET', $this->prepareRoute() . Uuid::randomHex(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testImportExportLogSearch(): void
    {
        $data = $this->prepareImportExportLogTestData(2);

        $invalidData = array_pop($data);

        $this->logRepository->create(array_values($data), $this->context);
        $searchData = array_pop($data);
        unset($searchData['config']);

        $filter = [];
        static::assertNotNull($invalidData);
        static::assertNotNull($searchData);
        foreach ($searchData as $key => $value) {
            $filter['filter'][$key] = $invalidData[$key];
            $this->getBrowser()->request('POST', $this->prepareRoute(true), $filter, [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
            $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static ::assertEquals(0, $content['total']);

            $filter['filter'][$key] = $value;
            $this->getBrowser()->request('POST', $this->prepareRoute(true), $filter, [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
            $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static ::assertEquals(1, $content['total']);
        }
    }

    public function testImportExportLogDelete(): void
    {
        $num = 3;
        $data = $this->prepareImportExportLogTestData($num);

        $this->logRepository->create(array_values($data), $this->context);
        $deleteId = array_column($data, 'id')[0];

        $this->getBrowser()->request('DELETE', $this->prepareRoute() . Uuid::randomHex(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_log');
        static::assertCount($num, $records);

        $this->getBrowser()->request('DELETE', $this->prepareRoute() . $deleteId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_log');
        static::assertCount($num, $records);
    }

    protected function prepareRoute(bool $search = false): string
    {
        $addPath = '';
        if ($search) {
            $addPath = '/search';
        }

        return '/api' . $addPath . '/import-export-log/';
    }

    /**
     * Prepare a defined number of test data.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function prepareImportExportLogTestData(int $num = 1): array
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
            $activities = [0 => 'import', 1 => 'export'];
        }

        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();
            $profile = $profiles[Uuid::fromHexToBytes($profileIds[$i % 2])];

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'activity' => $activities[$i % 2] ?? null,
                'state' => sprintf('state %d', $i),
                'userId' => $userIds[$i % 2],
                'profileId' => $profileIds[$i % 2],
                'fileId' => $fileIds[$i % 2],
                'username' => $users[Uuid::fromHexToBytes($userIds[$i % 2])]['username'],
                'profileName' => $profile['label'],
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
                'username' => sprintf('foobar%d', $i),
                'password' => sprintf('shopwarepw%d', $i),
                'firstName' => sprintf('Foo%d', $i),
                'lastName' => sprintf('Bar%d', $i),
                'email' => sprintf('fo%d@ob.ar', $i),
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

    /**
     * @param array<string, array<string, mixed>> $data
     *
     * @return array<int, mixed>
     */
    protected function rotateTestdata(array $data): array
    {
        array_push($data, array_shift($data));

        return array_values($data);
    }
}
