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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemStyleOptionLifecycleHandler::class)]
class ContentSystemStyleOptionLifecycleHandlerTest extends TestCase
{
    #[TestDox('install persists the app options')]
    public function testInstallPersists(): void
    {
        $context = $this->buildPersistContext();

        $this->handlerExpectingPersist($context)->install($context);
    }

    #[TestDox('update persists the app options')]
    public function testUpdatePersists(): void
    {
        $context = $this->buildPersistContext();

        $this->handlerExpectingPersist($context)->update($context);
    }

    #[TestDox('activate invalidates the registry so the now-live app options appear immediately')]
    public function testActivateInvalidates(): void
    {
        $this->handlerExpectingInvalidation()->activate($this->buildActivationContext());
    }

    #[TestDox('deactivate invalidates the registry')]
    public function testDeactivateInvalidates(): void
    {
        $this->handlerExpectingInvalidation()->deactivate($this->buildActivationContext());
    }

    #[TestDox('uninstall invalidates the registry')]
    public function testUninstallInvalidates(): void
    {
        $this->handlerExpectingInvalidation()->uninstall($this->buildRemovalContext());
    }

    #[TestDox('delete invalidates the registry on local removal without re-deactivating')]
    public function testDeleteInvalidates(): void
    {
        $this->handlerExpectingInvalidation()->delete($this->buildRemovalContext());
    }

    #[TestDox('propagates a persister failure on install rather than swallowing it')]
    public function testInstallPropagatesPersisterException(): void
    {
        $persister = static::createStub(ContentSystemStyleOptionPersister::class);
        $persister->method('persist')->willThrowException(new \RuntimeException('persist failed'));
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);

        $this->expectExceptionObject(new \RuntimeException('persist failed'));

        (new ContentSystemStyleOptionLifecycleHandler($persister, $registry))->install($this->buildPersistContext());
    }

    #[TestDox('propagates a registry failure on activate rather than swallowing it')]
    public function testActivatePropagatesRegistryException(): void
    {
        $persister = static::createStub(ContentSystemStyleOptionPersister::class);
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('invalidate')->willThrowException(new \RuntimeException('invalidate failed'));

        $this->expectExceptionObject(new \RuntimeException('invalidate failed'));

        (new ContentSystemStyleOptionLifecycleHandler($persister, $registry))->activate($this->buildActivationContext());
    }

    private function handlerExpectingPersist(AppPersistContext $context): ContentSystemStyleOptionLifecycleHandler
    {
        $persister = $this->createMock(ContentSystemStyleOptionPersister::class);
        $persister->expects($this->once())->method('persist')->with($context);

        $registry = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        return new ContentSystemStyleOptionLifecycleHandler($persister, $registry);
    }

    private function handlerExpectingInvalidation(): ContentSystemStyleOptionLifecycleHandler
    {
        $persister = $this->createMock(ContentSystemStyleOptionPersister::class);
        $persister->expects($this->never())->method('persist');

        $registry = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        return new ContentSystemStyleOptionLifecycleHandler($persister, $registry);
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

    private function buildRemovalContext(): AppRemovalContext
    {
        return new AppRemovalContext($this->buildApp(), Context::createDefaultContext());
    }

    private function buildPersistContext(): AppPersistContext
    {
        return new AppPersistContext(
            manifest: static::createStub(Manifest::class),
            app: $this->buildApp(),
            context: Context::createDefaultContext(),
            appFilesystem: static::createStub(Filesystem::class),
            defaultLocale: 'en-GB',
        );
    }
}
