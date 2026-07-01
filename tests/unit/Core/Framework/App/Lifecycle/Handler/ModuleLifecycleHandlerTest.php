<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\ModuleLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\Admin;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\MainModule;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\Module;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(ModuleLifecycleHandler::class)]
class ModuleLifecycleHandlerTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testPersistDoesNothingWithoutAppSecret(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        $persister = new ModuleLifecycleHandler($appRepository);
        $persister->install($this->buildContext(hasSecret: false));

        static::assertSame([], $appRepository->updates);
    }

    public function testPersistClearsModulesWhenNoAdminSection(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        $persister = new ModuleLifecycleHandler($appRepository);
        $persister->install($this->buildContext(hasSecret: true, admin: null));

        static::assertCount(1, $appRepository->updates);
        static::assertSame([[
            'id' => $this->ids->get('app'),
            'mainModule' => null,
            'modules' => [],
        ]], $appRepository->updates[0]);
    }

    public function testPersistModulesWithMainModuleOnly(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        $admin = Admin::fromArray([
            'mainModule' => MainModule::fromArray(['source' => 'https://example.com/main']),
            'modules' => [],
        ]);

        $persister = new ModuleLifecycleHandler($appRepository);
        $persister->install($this->buildContext(hasSecret: true, admin: $admin));

        static::assertCount(1, $appRepository->updates);
        static::assertSame([[
            'id' => $this->ids->get('app'),
            'mainModule' => ['source' => 'https://example.com/main'],
            'modules' => [],
        ]], $appRepository->updates[0]);
    }

    public function testPersistModulesWithModulesOnly(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        $admin = Admin::fromArray([
            'mainModule' => null,
            'modules' => [
                Module::fromArray([
                    'name' => 'module1',
                    'label' => ['en-GB' => 'Module 1'],
                    'source' => 'https://example.com/module1',
                    'parent' => 'sw-catalogue',
                    'position' => 1,
                ]),
                Module::fromArray([
                    'name' => 'module2',
                    'label' => ['en-GB' => 'Module 2'],
                    'source' => null,
                    'parent' => 'sw-order',
                    'position' => 2,
                ]),
            ],
        ]);

        $persister = new ModuleLifecycleHandler($appRepository);
        $persister->install($this->buildContext(hasSecret: true, admin: $admin));

        static::assertCount(1, $appRepository->updates);
        static::assertSame([[
            'id' => $this->ids->get('app'),
            'mainModule' => null,
            'modules' => [
                [
                    'label' => ['en-GB' => 'Module 1'],
                    'source' => 'https://example.com/module1',
                    'name' => 'module1',
                    'parent' => 'sw-catalogue',
                    'position' => 1,
                ],
                [
                    'label' => ['en-GB' => 'Module 2'],
                    'source' => null,
                    'name' => 'module2',
                    'parent' => 'sw-order',
                    'position' => 2,
                ],
            ],
        ]], $appRepository->updates[0]);
    }

    public function testPersistModulesWithMainModuleAndModules(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        $admin = Admin::fromArray([
            'mainModule' => MainModule::fromArray(['source' => 'https://example.com/main']),
            'modules' => [
                Module::fromArray([
                    'name' => 'module1',
                    'label' => ['en-GB' => 'Module 1'],
                    'source' => 'https://example.com/module1',
                    'parent' => 'sw-catalogue',
                    'position' => 1,
                ]),
            ],
        ]);

        $persister = new ModuleLifecycleHandler($appRepository);
        $persister->install($this->buildContext(hasSecret: true, admin: $admin));

        static::assertCount(1, $appRepository->updates);
        static::assertSame([[
            'id' => $this->ids->get('app'),
            'mainModule' => ['source' => 'https://example.com/main'],
            'modules' => [
                [
                    'label' => ['en-GB' => 'Module 1'],
                    'source' => 'https://example.com/module1',
                    'name' => 'module1',
                    'parent' => 'sw-catalogue',
                    'position' => 1,
                ],
            ],
        ]], $appRepository->updates[0]);
    }

    private function buildContext(bool $hasSecret, ?Admin $admin = null): AppPersistContext
    {
        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setActive(true);

        if ($hasSecret) {
            $app->setAppSecret('s3cr3t');
        }

        $manifest = $this->createMock(Manifest::class);
        $manifest->method('getAdmin')->willReturn($admin);

        return new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(),
            defaultLocale: 'en-GB',
        );
    }
}
