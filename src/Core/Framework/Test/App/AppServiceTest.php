<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Finder\Finder;

class AppServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPluginRegistryTestBehaviour;

    /**
     * @var AppService
     */
    private $appService;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $actionButtonRepository;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->actionButtonRepository = $this->getContainer()->get('app_action_button.repository');

        $this->appService = new AppService(
            new AppLifecycleIterator(
                $this->appRepository,
                new AppLoader(
                    __DIR__ . '/Manifest/_fixtures/test',
                    $this->getContainer()->getParameter('kernel.project_dir'),
                    $this->getContainer()->get(ConfigReader::class)
                )
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );

        $this->context = Context::createDefaultContext();
    }

    public function testRefreshInstallsNewApp(): void
    {
        $this->appService->doRefreshApps(true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('test', $apps->first()->getName());

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
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'test',
            ],
        ]], $this->context);

        $this->appService->doRefreshApps(true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('test', $apps->first()->getName());
        static::assertEquals('1.0.0', $apps->first()->getVersion());
        static::assertNotEquals('test', $apps->first()->getTranslation('label'));

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
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'test',
            ],
        ]], $this->context);

        $this->appService->doRefreshApps(true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('test', $apps->first()->getName());
        static::assertEquals('1.0.0', $apps->first()->getVersion());
        static::assertEquals('test', $apps->first()->getTranslation('label'));
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
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'deleteTest',
            ],
        ]], $this->context);

        static::assertCount(1, $this->appRepository->searchIds(new Criteria([]), $this->context)->getIds());

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
                    'writeAccess' => false,
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
                    'writeAccess' => false,
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
                new AppLoader(
                    __DIR__ . '/Manifest/_fixtures',
                    $this->getContainer()->getParameter('kernel.project_dir'),
                    $this->getContainer()->get(ConfigReader::class)
                )
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );
        $refreshableApps = $appService->getRefreshableAppInfo($this->context);

        static::assertCount(9, $refreshableApps->getToBeInstalled());
        static::assertCount(1, $refreshableApps->getToBeUpdated());
        static::assertCount(1, $refreshableApps->getToBeDeleted());

        static::assertInstanceOf(Manifest::class, $refreshableApps->getToBeInstalled()[0]);
        static::assertInstanceOf(Manifest::class, $refreshableApps->getToBeUpdated()[0]);
        static::assertEquals('deleteTest', $refreshableApps->getToBeDeleted()[0]);
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
                new AppLoader(
                    $appDir,
                    $this->getContainer()->getParameter('kernel.project_dir'),
                    $this->getContainer()->get(ConfigReader::class)
                )
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );

        $fails = $appService->doRefreshApps(true, $this->context);
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(12, $manifests); // 2 are not parsable
        static::assertCount(7, $apps);
        static::assertCount(3, $fails);
    }

    private function assertDefaultActionButtons(): void
    {
        $actionButtons = $this->actionButtonRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $actionButtons);
        $actionNames = array_map(function (ActionButtonEntity $actionButton) {
            return $actionButton->getAction();
        }, $actionButtons->getElements());

        static::assertContains('viewOrder', $actionNames);
        static::assertContains('doStuffWithProducts', $actionNames);
    }
}
