<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\ContentSystemElementTypeLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemElementTypePersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemElementTypeLifecycleHandler::class)]
class ContentSystemElementTypeLifecycleHandlerTest extends TestCase
{
    #[TestDox('install persists the app element types')]
    public function testInstallPersists(): void
    {
        $context = $this->buildPersistContext();
        $persister = $this->createMock(ContentSystemElementTypePersister::class);
        $persister->expects($this->once())->method('persist')->with($context);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        (new ContentSystemElementTypeLifecycleHandler($persister, $registry))->install($context);
    }

    #[TestDox('update persists the app element types')]
    public function testUpdatePersists(): void
    {
        $context = $this->buildPersistContext();
        $persister = $this->createMock(ContentSystemElementTypePersister::class);
        $persister->expects($this->once())->method('persist')->with($context);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        (new ContentSystemElementTypeLifecycleHandler($persister, $registry))->update($context);
    }

    #[TestDox('activation invalidates the registry so the app element types become available')]
    public function testActivateInvalidates(): void
    {
        $this->handlerExpectingInvalidation()->activate($this->buildActivationContext());
    }

    #[TestDox('deactivation invalidates the registry so the app element types disappear')]
    public function testDeactivateInvalidates(): void
    {
        $this->handlerExpectingInvalidation()->deactivate($this->buildActivationContext());
    }

    #[TestDox('uninstall invalidates the registry')]
    public function testUninstallInvalidates(): void
    {
        $this->handlerExpectingInvalidation()->uninstall($this->buildRemovalContext());
    }

    #[TestDox('local deletion invalidates the registry')]
    public function testDeleteInvalidates(): void
    {
        $this->handlerExpectingInvalidation()->delete($this->buildRemovalContext());
    }

    #[TestDox('a persister failure is propagated')]
    public function testPropagatesPersisterException(): void
    {
        $exception = new \RuntimeException('persist failed');
        $persister = static::createStub(ContentSystemElementTypePersister::class);
        $persister->method('persist')->willThrowException($exception);

        $this->expectExceptionObject($exception);
        (new ContentSystemElementTypeLifecycleHandler($persister, static::createStub(AbstractContentSystemElementTypeRegistry::class)))
            ->install($this->buildPersistContext());
    }

    #[TestDox('a registry invalidation failure is propagated')]
    public function testPropagatesRegistryException(): void
    {
        $exception = new \RuntimeException('invalidate failed');
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('invalidate')->willThrowException($exception);

        $this->expectExceptionObject($exception);
        (new ContentSystemElementTypeLifecycleHandler(static::createStub(ContentSystemElementTypePersister::class), $registry))
            ->activate($this->buildActivationContext());
    }

    private function handlerExpectingInvalidation(): ContentSystemElementTypeLifecycleHandler
    {
        $persister = $this->createMock(ContentSystemElementTypePersister::class);
        $persister->expects($this->never())->method('persist');

        $registry = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        return new ContentSystemElementTypeLifecycleHandler($persister, $registry);
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
