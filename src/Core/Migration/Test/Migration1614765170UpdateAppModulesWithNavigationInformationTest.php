<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1614765170UpdateAppModulesWithNavigationInformation;

class Migration1614765170UpdateAppModulesWithNavigationInformationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private EntityRepositoryInterface $appRepository;

    private Context $context;

    public function setup(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testMigrationAddsDefaultValuesToMultipleModulesInOneApp(): void
    {
        $appId = $this->insertAppWithModule('testApp', [
            [
                'source' => 'http://testApp-module-1',
                'label' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
            ],
            [
                'source' => 'http://testApp-module-2',
                'label' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
            ],
        ]);

        (new Migration1614765170UpdateAppModulesWithNavigationInformation())
            ->update($this->connection);

        $app = $this->appRepository->search(new Criteria([$appId]), $this->context)->first();

        static::assertEquals([
            [
                'source' => 'http://testApp-module-1',
                'label' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
                'parent' => null,
                'position' => 1,
            ],
            [
                'source' => 'http://testApp-module-2',
                'label' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
                'parent' => null,
                'position' => 1,
            ],
        ], $app->getModules());
    }

    public function testMigrationAddsDefaultValuesModulesInMultipleApps(): void
    {
        $firstAppId = $this->insertAppWithModule('testApp1', [
            [
                'source' => 'http://testApp1-module',
                'label' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
            ],
        ]);
        $secondAppId = $this->insertAppWithModule('testApp2', [
            [
                'source' => 'http://testApp2-module',
                'label' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
            ],
        ]);

        (new Migration1614765170UpdateAppModulesWithNavigationInformation())
            ->update($this->connection);

        $apps = $this->appRepository->search(new Criteria([$firstAppId, $secondAppId]), $this->context);

        $firstApp = $apps->get($firstAppId);
        static::assertEquals([
            [
                'source' => 'http://testApp1-module',
                'label' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
                'parent' => null,
                'position' => 1,
            ],
        ], $firstApp->getModules());

        $secondApp = $apps->get($secondAppId);
        static::assertEquals([
            [
                'source' => 'http://testApp2-module',
                'label' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
                'parent' => null,
                'position' => 1,
            ],
        ], $secondApp->getModules());
    }

    public function testItWouldNotTouchExistingValues(): void
    {
        $appId = $this->insertAppWithModule('testApp', [
            [
                'source' => 'http://testApp-module-1',
                'label' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
                'position' => 50,
                'parent' => 'sw-catalogue',
            ],
        ]);

        (new Migration1614765170UpdateAppModulesWithNavigationInformation())
            ->update($this->connection);

        $app = $this->appRepository->search(new Criteria([$appId]), $this->context)->first();

        static::assertEquals([
            [
                'source' => 'http://testApp-module-1',
                'label' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
                'position' => 50,
                'parent' => 'sw-catalogue',
            ],
        ], $app->getModules());
    }

    private function insertAppWithModule(string $name, ?array $modules): string
    {
        $appId = Uuid::randomHex();
        $this->appRepository->create([
            [
                'id' => $appId,
                'name' => $name,
                'label' => $name,
                'path' => realpath(__DIR__ . '/../../Framework/Test/App/Manifest/_fixtures/test'),
                'active' => true,
                'modules' => $modules,
                'version' => '1.0.0',
                'integration' => [
                    'label' => 'App1',
                    'writeAccess' => false,
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'App1',
                ],
            ],
        ], $this->context);

        return $appId;
    }
}
