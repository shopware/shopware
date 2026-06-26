<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\ContentSystemStyleOptionLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemStyleOptionPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[CoversClass(ContentSystemStyleOptionLifecycleHandler::class)]
class ContentSystemStyleOptionLifecycleHandlerTest extends TestCase
{
    #[TestDox('install persists the app options')]
    public function testInstallPersists(): void
    {
        $context = $this->buildPersistContext();

        $persister = $this->createMock(ContentSystemStyleOptionPersister::class);
        $persister->expects($this->once())->method('persist')->with($context);

        $registry = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        (new ContentSystemStyleOptionLifecycleHandler($persister, $registry))->install($context);
    }

    #[TestDox('update persists the app options')]
    public function testUpdatePersists(): void
    {
        $context = $this->buildPersistContext();

        $persister = $this->createMock(ContentSystemStyleOptionPersister::class);
        $persister->expects($this->once())->method('persist')->with($context);

        $registry = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        (new ContentSystemStyleOptionLifecycleHandler($persister, $registry))->update($context);
    }

    #[TestDox('activate invalidates the registry so the now-live app options appear immediately')]
    public function testActivateInvalidates(): void
    {
        $context = $this->buildActivationContext();

        $persister = $this->createMock(ContentSystemStyleOptionPersister::class);
        $persister->expects($this->never())->method('persist');

        $registry = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        (new ContentSystemStyleOptionLifecycleHandler($persister, $registry))->activate($context);
    }

    #[TestDox('deactivate invalidates the registry')]
    public function testDeactivateInvalidates(): void
    {
        $context = $this->buildActivationContext();

        $persister = $this->createMock(ContentSystemStyleOptionPersister::class);
        $persister->expects($this->never())->method('persist');

        $registry = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        (new ContentSystemStyleOptionLifecycleHandler($persister, $registry))->deactivate($context);
    }

    #[TestDox('uninstall invalidates the registry')]
    public function testUninstallInvalidates(): void
    {
        $app = $this->buildApp();
        $context = new AppRemovalContext($app, Context::createDefaultContext());

        $persister = $this->createMock(ContentSystemStyleOptionPersister::class);
        $persister->expects($this->never())->method('persist');

        $registry = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        (new ContentSystemStyleOptionLifecycleHandler($persister, $registry))->uninstall($context);
    }

    #[TestDox('delete invalidates the registry on local removal without re-deactivating')]
    public function testDeleteInvalidates(): void
    {
        $app = $this->buildApp();
        $context = new AppRemovalContext($app, Context::createDefaultContext());

        $persister = $this->createMock(ContentSystemStyleOptionPersister::class);
        $persister->expects($this->never())->method('persist');

        $registry = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        (new ContentSystemStyleOptionLifecycleHandler($persister, $registry))->delete($context);
    }

    private function buildApp(): AppEntity
    {
        $app = new AppEntity();
        $app->setId('app-id');
        $app->setName('DemoApp');

        return $app;
    }

    private function buildActivationContext(): AppActivationContext
    {
        return new AppActivationContext($this->buildApp(), Context::createDefaultContext());
    }

    private function buildPersistContext(): AppPersistContext
    {
        return new AppPersistContext(
            manifest: $this->createMock(Manifest::class),
            app: $this->buildApp(),
            context: Context::createDefaultContext(),
            appFilesystem: $this->createMock(Filesystem::class),
            defaultLocale: 'en-GB',
        );
    }
}
