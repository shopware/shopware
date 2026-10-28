<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppDirectory;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopware\Core\Framework\App\Event\AppUploadedEvent;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AppExtensionService;
use Shopware\Core\Framework\Store\Services\ExtensionRemovalValidatorInterface;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AppExtensionService::class)]
class AppExtensionServiceTest extends TestCase
{
    public function testUploadStoresAppAndDispatchesUploadedEvent(): void
    {
        $context = Context::createDefaultContext();
        $file = new UploadedFile(__DIR__ . '/../../Plugin/_fixtures/archives/App.zip', 'App.zip', null, null, true);

        $appDirectory = $this->createMock(AppDirectory::class);
        $appDirectory->expects($this->once())->method('store')->with($file->getPathname());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn (AppUploadedEvent $event): bool => $event->filename === 'App.zip'
                && $event->appName === 'SwagApp'
                && $event->appVersion === '1.0.0'
                && $event->context === $context));

        $service = $this->createService(appDirectory: $appDirectory, dispatcher: $dispatcher);

        static::assertSame('SwagApp', $service->upload($file, $context));
    }

    public function testActivatesInstalledApp(): void
    {
        $app = AppFixture::createAppEntity('TestApp', 'app-id');
        $context = Context::createDefaultContext();

        $appStorage = $this->createMock(AppStorage::class);
        $appStorage
            ->expects($this->once())
            ->method('findByName')
            ->with('TestApp', $context)
            ->willReturn($app);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle
            ->expects($this->once())
            ->method('activate')
            ->with('app-id', $context);

        $service = $this->createService(appLifecycle: $appLifecycle, appStorage: $appStorage);

        $service->activateExtension('TestApp', $context);
    }

    public function testDeactivatesInstalledApp(): void
    {
        $app = AppFixture::createAppEntity('TestApp', 'app-id');
        $context = Context::createDefaultContext();

        $appStorage = $this->createMock(AppStorage::class);
        $appStorage
            ->expects($this->once())
            ->method('findByName')
            ->with('TestApp', $context)
            ->willReturn($app);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle
            ->expects($this->once())
            ->method('deactivate')
            ->with('app-id', $context);

        $service = $this->createService(appLifecycle: $appLifecycle, appStorage: $appStorage);

        $service->deactivateExtension('TestApp', $context);
    }

    public function testUninstallAsksEveryRemovalValidatorBeforeUninstalling(): void
    {
        $app = AppFixture::createAppEntity('TestApp', 'app-id');
        $context = Context::createDefaultContext();

        $appStorage = static::createStub(AppStorage::class);
        $appStorage->method('findByName')->willReturn($app);

        $firstValidator = $this->createMock(ExtensionRemovalValidatorInterface::class);
        $firstValidator
            ->expects($this->once())
            ->method('validateCanBeRemoved')
            ->with('TestApp', 'app-id', $context);

        $secondValidator = $this->createMock(ExtensionRemovalValidatorInterface::class);
        $secondValidator
            ->expects($this->once())
            ->method('validateCanBeRemoved')
            ->with('TestApp', 'app-id', $context);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle
            ->expects($this->once())
            ->method('uninstall')
            ->with('TestApp', ['id' => 'app-id'], $context, false);

        $service = $this->createService(
            appLifecycle: $appLifecycle,
            appStorage: $appStorage,
            removalValidators: [$firstValidator, $secondValidator]
        );

        $service->uninstallExtension('TestApp', $context);
    }

    public function testRejectedRemovalKeepsTheAppInstalled(): void
    {
        $app = AppFixture::createAppEntity('TestApp', 'app-id');
        $context = Context::createDefaultContext();

        $appStorage = static::createStub(AppStorage::class);
        $appStorage->method('findByName')->willReturn($app);

        $validator = static::createStub(ExtensionRemovalValidatorInterface::class);
        $validator->method('validateCanBeRemoved')->willThrowException(StoreException::extensionThemeStillInUse('app-id'));

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('uninstall');

        $service = $this->createService(
            appLifecycle: $appLifecycle,
            appStorage: $appStorage,
            removalValidators: [$validator]
        );

        $this->expectExceptionObject(StoreException::extensionThemeStillInUse('app-id'));

        $service->uninstallExtension('TestApp', $context);
    }

    /**
     * @param iterable<ExtensionRemovalValidatorInterface> $removalValidators
     */
    private function createService(
        ?AbstractAppLifecycle $appLifecycle = null,
        ?AppStorage $appStorage = null,
        iterable $removalValidators = [],
        ?AppDirectory $appDirectory = null,
        ?EventDispatcherInterface $dispatcher = null,
    ): AppExtensionService {
        return new AppExtensionService(
            static::createStub(AppLoader::class),
            $appLifecycle ?? static::createStub(AbstractAppLifecycle::class),
            $appStorage ?? static::createStub(AppStorage::class),
            $removalValidators,
            static::createStub(AppConfirmationDeltaProvider::class),
            $appDirectory ?? static::createStub(AppDirectory::class),
            $dispatcher ?? static::createStub(EventDispatcherInterface::class),
        );
    }
}
