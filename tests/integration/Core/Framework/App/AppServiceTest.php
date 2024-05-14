<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class AppServiceTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private AppService $appService;

    private EntityRepository $appRepository;

    private Context $context;

    private EntityRepository $actionButtonRepository;

    protected function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->actionButtonRepository = $this->getContainer()->get('app_action_button.repository');

        $this->appService = new AppService(
            new AppLifecycleIterator(
                $this->appRepository,
                $this->getAppLoader(__DIR__ . '/Manifest/_fixtures/test')
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );

        $this->context = Context::createDefaultContext();
    }

    public function testRefreshInstallsNewApp(): void
    {
        $this->appService->doRefreshApps(true, $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $first = $apps->first();
        static::assertInstanceOf(AppEntity::class, $first);
        static::assertSame('test', $first->getName());

        $this->assertDefaultActionButtons();
    }

    public function testRefreshUpdatesApp(): void
    {
        $this->appRepository->create([[
            'name' => 'test',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'test',
            ],
        ]], $this->context);

        $this->appService->doRefreshApps(true, $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $first = $apps->first();
        static::assertInstanceOf(AppEntity::class, $first);
        static::assertSame('test', $first->getName());
        static::assertSame('1.0.0', $first->getVersion());
        static::assertNotEquals('test', $first->getTranslation('label'));

        $this->assertDefaultActionButtons();
    }

    public function testRefreshAppIsUntouched(): void
    {
        $this->appRepository->create([[
            'name' => 'test',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '1.0.0',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'test',
            ],
        ]], $this->context);

        $this->appService->doRefreshApps(true, $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $first = $apps->first();
        static::assertInstanceOf(AppEntity::class, $first);
        static::assertSame('test', $first->getName());
        static::assertSame('1.0.0', $first->getVersion());
        static::assertSame('test', $first->getTranslation('label'));
    }

    public function testRefreshDeletesApp(): void
    {
        $appId = Uuid::randomHex();
        $this->appRepository->create([[
            'id' => $appId,
            'name' => 'deleteTest',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'deleteTest',
            ],
        ]], $this->context);

        static::assertCount(1, $this->appRepository->searchIds(new Criteria(), $this->context)->getIds());

        $this->appService->doRefreshApps(true, $this->context);

        $apps = $this->appRepository->searchIds(new Criteria([$appId]), $this->context)->getIds();
        static::assertCount(0, $apps);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $apps = $this->actionButtonRepository->searchIds($criteria, $this->context)->getIds();
        static::assertCount(0, $apps);
    }

    public function testGetRefreshableApps(): void
    {
        $this->appRepository->create([
            [
                'name' => 'deleteTest',
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'actionButtons' => [
                    [
                        'entity' => 'order',
                        'view' => 'detail',
                        'action' => 'test',
                        'label' => 'test',
                        'url' => 'test.com',
                    ],
                ],
                'integration' => [
                    'label' => 'test',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'deleteTest',
                ],
            ],
            [
                'name' => 'test',
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'actionButtons' => [
                    [
                        'entity' => 'order',
                        'view' => 'detail',
                        'action' => 'test',
                        'label' => 'test',
                        'url' => 'test.com',
                    ],
                ],
                'integration' => [
                    'label' => 'test',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'test',
                ],
            ],
        ], $this->context);

        $appService = new AppService(
            new AppLifecycleIterator(
                $this->appRepository,
                $this->getAppLoader(__DIR__ . '/Manifest/_fixtures')
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );
        $refreshableApps = $appService->getRefreshableAppInfo($this->context);

        static::assertCount(7, $refreshableApps->getToBeInstalled());
        static::assertCount(1, $refreshableApps->getToBeUpdated());
        static::assertCount(1, $refreshableApps->getToBeDeleted());

        static::assertInstanceOf(Manifest::class, array_values($refreshableApps->getToBeInstalled())[0]);
        static::assertInstanceOf(Manifest::class, array_values($refreshableApps->getToBeUpdated())[0]);
        static::assertSame('deleteTest', array_values($refreshableApps->getToBeDeleted())[0]);
    }

    public function testInstallFailureDoesNotAffectAllApps(): void
    {
        $appDir = __DIR__ . '/Manifest/_fixtures';
        $finder = new Finder();
        $finder->in($appDir)
            ->depth('<= 1')
            ->name('manifest.xml');

        $manifests = [];
        foreach ($finder->files() as $xml) {
            $manifests[] = $xml->getPathname();
        }

        $appService = new AppService(
            new AppLifecycleIterator(
                $this->appRepository,
                $this->getAppLoader($appDir)
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );

        $fails = $appService->doRefreshApps(true, $this->context);
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(8, $manifests); // 2 are not parsable
        static::assertCount(6, $apps);
        static::assertCount(2, $fails);
    }

    private function assertDefaultActionButtons(): void
    {
        $actionButtons = $this->actionButtonRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $actionButtons);

        $actionNames = $actionButtons->map(fn (ActionButtonEntity $actionButton) => $actionButton->getAction());

        static::assertContains('viewOrder', $actionNames);
        static::assertContains('doStuffWithProducts', $actionNames);
    }
}
