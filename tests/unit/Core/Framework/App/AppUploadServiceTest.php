<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\AppUploadService;
use Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopware\Core\Framework\App\Event\AppUploadValidationEvent;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\App\Source\UploadSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppUploadService::class)]
class AppUploadServiceTest extends TestCase
{
    private const MANIFEST = __DIR__ . '/_fixtures/App/TestApp/manifest.xml';

    public function testInvalidArchiveIsRejectedBeforeSourceStorage(): void
    {
        $extractor = $this->createMock(AppExtractor::class);
        $extractor->expects($this->once())->method('extract')->willThrowException(AppException::xmlParsingException('upload.zip', 'invalid zip'));
        $source = $this->createMock(UploadSource::class);
        $source->expects($this->never())->method('storeArchive');

        $this->expectException(AppException::class);
        $this->createService($extractor, $source)->upload($this->uploadedFile(), Context::createDefaultContext(), 'private-app');
    }

    public function testInvalidManifestIsRejectedBeforeSourceStorage(): void
    {
        $directory = sys_get_temp_dir() . '/invalid-app-' . bin2hex(random_bytes(5));
        (new Filesystem())->mkdir($directory);
        (new Filesystem())->dumpFile($directory . '/manifest.xml', '<not-a-manifest');

        $extractor = static::createStub(AppExtractor::class);
        $extractor->method('extract')->willReturn($directory);
        $source = $this->createMock(UploadSource::class);
        $source->expects($this->never())->method('storeArchive');

        try {
            $this->expectException(AppException::class);
            $this->createService($extractor, $source)->upload($this->uploadedFile(), Context::createDefaultContext(), 'private-app');
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    public function testValidationHookCanRejectBeforeStorageAndLifecycle(): void
    {
        $source = $this->createMock(UploadSource::class);
        $source->expects($this->never())->method('storeArchive');
        $lifecycle = $this->createMock(AbstractAppLifecycle::class);
        $lifecycle->expects($this->never())->method('install');
        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects($this->once())->method('dispatch')->with(static::isInstanceOf(AppUploadValidationEvent::class))->willThrowException(new \RuntimeException('rejected'));

        $this->expectExceptionObject(new \RuntimeException('rejected'));
        $this->createService(lifecycle: $lifecycle, source: $source, events: $events)->upload(
            $this->uploadedFile(),
            Context::createDefaultContext(),
            'private-app'
        );
    }

    public function testSuccessfulFirstInstallUsesLifecycleAndSelectedStorageSource(): void
    {
        $manifest = Manifest::createFromXmlFile(self::MANIFEST);
        $source = $this->createMock(UploadSource::class);
        $source->method('supports')->willReturn(true);
        $source->expects($this->once())->method('storeArchive')->willReturn('private-apps/TestApp/TestApp.zip');
        $lifecycle = $this->createMock(AbstractAppLifecycle::class);
        $lifecycle->expects($this->once())->method('install')->with(
            static::callback(static fn (Manifest $actual): bool => $actual->getSourceType() === 'private-app' && $actual->getPath() === 'private-apps/TestApp/TestApp.zip'),
            static::isInstanceOf(AppInstallParameters::class),
            static::isInstanceOf(Context::class)
        );
        $provider = static::createStub(AbstractExtensionDataProvider::class);
        $provider->method('getAppEntityFromTechnicalName')->willThrowException(StoreException::extensionNotFoundFromTechnicalName('TestApp'));

        $this->createService(lifecycle: $lifecycle, source: $source, provider: $provider)->upload(
            $this->uploadedFile(),
            Context::createDefaultContext(),
            'private-app'
        );
    }

    public function testSuccessfulUpdateAcceptsPermissionsAndStoresUpdatedArchive(): void
    {
        $app = new AppEntity();
        $app->setId('12345678901234567890123456789012');
        $app->setName('TestApp');
        $app->setPath('private-apps/TestApp/TestApp.zip');
        $source = $this->createMock(UploadSource::class);
        $source->method('supports')->willReturn(true);
        $source->expects($this->once())->method('backup')->with($app)->willReturn(sys_get_temp_dir() . '/no-existing-backup');
        $source->expects($this->once())->method('storeArchive')->willReturn('private-apps/TestApp/TestApp.zip');
        $confirmation = static::createStub(AppConfirmationDeltaProvider::class);
        $confirmation->method('requiresRenewedConsent')->willReturn(true);
        $lifecycle = $this->createMock(AbstractAppLifecycle::class);
        $lifecycle->expects($this->once())->method('update')->with(
            static::isInstanceOf(Manifest::class),
            static::callback(static fn (AppUpdateParameters $parameters): bool => $parameters->acceptPermissions),
            ['id' => $app->getId()],
            static::isInstanceOf(Context::class)
        );

        $this->createService(lifecycle: $lifecycle, source: $source, provider: $this->providerReturning($app), confirmation: $confirmation)->upload(
            $this->uploadedFile(),
            Context::createDefaultContext(),
            'private-app',
            acceptPermissions: true,
            updateOnly: true
        );
    }

    public function testUpdateRequiresConsentBeforeStorage(): void
    {
        $app = new AppEntity();
        $app->setId('12345678901234567890123456789012');
        $app->setName('TestApp');
        $source = $this->createMock(UploadSource::class);
        $source->method('supports')->willReturn(true);
        $source->expects($this->never())->method('backup');
        $source->expects($this->never())->method('storeArchive');
        $confirmation = static::createStub(AppConfirmationDeltaProvider::class);
        $confirmation->method('requiresRenewedConsent')->willReturn(true);

        $this->expectException(StoreException::class);
        $this->createService(source: $source, provider: $this->providerReturning($app), confirmation: $confirmation)->upload(
            $this->uploadedFile(),
            Context::createDefaultContext(),
            'private-app',
            updateOnly: true
        );
    }

    public function testFirstInstallRequestDoesNotSilentlyUpdateAnExistingApp(): void
    {
        $app = new AppEntity();
        $app->setId('12345678901234567890123456789012');
        $app->setName('TestApp');
        $source = $this->createMock(UploadSource::class);
        $source->expects($this->never())->method('backup');
        $source->expects($this->never())->method('storeArchive');

        $this->expectExceptionObject(AppException::alreadyInstalled('TestApp'));
        $this->createService(source: $source, provider: $this->providerReturning($app))->upload(
            $this->uploadedFile(),
            Context::createDefaultContext(),
            'private-app'
        );
    }

    public function testLifecycleFailureRestoresPreviousStoredArchive(): void
    {
        $app = new AppEntity();
        $app->setId('12345678901234567890123456789012');
        $app->setName('TestApp');
        $app->setPath('private-apps/TestApp/TestApp.zip');
        $backup = sys_get_temp_dir() . '/app-upload-backup-' . bin2hex(random_bytes(5));
        (new Filesystem())->mkdir($backup);
        (new Filesystem())->copy(self::MANIFEST, $backup . '/manifest.xml');
        $source = $this->createMock(UploadSource::class);
        $source->method('supports')->willReturn(true);
        $source->method('backup')->willReturn($backup);
        $source->method('storeArchive')->willReturn('private-apps/TestApp/TestApp.zip');
        $source->expects($this->once())->method('restore')->with($app, $backup);
        $lifecycle = $this->createMock(AbstractAppLifecycle::class);
        $updateCalls = 0;
        $lifecycle->expects($this->exactly(2))->method('update')->willReturnCallback(static function () use (&$updateCalls): void {
            if (++$updateCalls === 1) {
                throw new \RuntimeException('lifecycle failed');
            }
        });

        try {
            $this->expectExceptionObject(new \RuntimeException('lifecycle failed'));
            $this->createService(lifecycle: $lifecycle, source: $source, provider: $this->providerReturning($app))->upload(
                $this->uploadedFile(),
                Context::createDefaultContext(),
                'private-app',
                acceptPermissions: true,
                updateOnly: true
            );
        } finally {
            (new Filesystem())->remove($backup);
        }
    }

    public function testFirstInstallLifecycleFailureRemovesStoredArchive(): void
    {
        $source = $this->createMock(UploadSource::class);
        $source->method('supports')->willReturn(true);
        $source->method('storeArchive')->willReturn('/stored/TestApp');
        $source->expects($this->once())->method('removeByManifest');
        $lifecycle = $this->createMock(AbstractAppLifecycle::class);
        $lifecycle->expects($this->once())->method('install')->willThrowException(new \RuntimeException('install failed'));

        $this->expectExceptionObject(new \RuntimeException('install failed'));
        $this->createService(lifecycle: $lifecycle, source: $source)->upload(
            $this->uploadedFile(),
            Context::createDefaultContext(),
            'private-app'
        );
    }

    public function testUpdateOnlyRequestWithoutExistingAppFails(): void
    {
        $source = $this->createMock(UploadSource::class);
        $source->method('supports')->willReturn(true);
        $source->expects($this->never())->method('storeArchive');

        $this->expectExceptionObject(AppException::notFound('minimal'));
        $this->createService(source: $source)->upload(
            $this->uploadedFile(),
            Context::createDefaultContext(),
            'private-app',
            updateOnly: true
        );
    }

    public function testStoreErrorDuringAppLookupIsRethrown(): void
    {
        $source = $this->createMock(UploadSource::class);
        $source->method('supports')->willReturn(true);
        $source->expects($this->never())->method('storeArchive');

        $error = StoreException::couldNotUploadExtensionCorrectly();
        $provider = static::createStub(AbstractExtensionDataProvider::class);
        $provider->method('getAppEntityFromTechnicalName')->willThrowException($error);

        $this->expectExceptionObject($error);
        $this->createService(source: $source, provider: $provider)->upload(
            $this->uploadedFile(),
            Context::createDefaultContext(),
            'private-app'
        );
    }

    private function createService(
        ?AppExtractor $extractor = null,
        ?UploadSource $source = null,
        ?AbstractAppLifecycle $lifecycle = null,
        ?EventDispatcherInterface $events = null,
        ?AbstractExtensionDataProvider $provider = null,
        ?AppConfirmationDeltaProvider $confirmation = null,
    ): AppUploadService {
        if ($extractor === null) {
            $extractor = static::createStub(AppExtractor::class);
            $extractor->method('extract')->willReturn(\dirname(self::MANIFEST));
        }
        if ($source === null) {
            $source = static::createStub(UploadSource::class);
            $source->method('supports')->willReturn(true);
            $source->method('storeArchive')->willReturn('private-apps/TestApp/TestApp.zip');
        }
        $resolver = static::createStub(SourceResolver::class);
        $resolver->method('uploadSourceForManifest')->willReturn($source);
        $lifecycle ??= static::createStub(AbstractAppLifecycle::class);
        $events ??= static::createStub(EventDispatcherInterface::class);
        if ($provider === null) {
            $provider = static::createStub(AbstractExtensionDataProvider::class);
            $provider->method('getAppEntityFromTechnicalName')->willThrowException(StoreException::extensionNotFoundFromTechnicalName('TestApp'));
        }

        return new AppUploadService(
            $extractor,
            $lifecycle,
            $provider,
            $resolver,
            $confirmation ?? static::createStub(AppConfirmationDeltaProvider::class),
            $events,
            new Filesystem(),
            sys_get_temp_dir()
        );
    }

    private function providerReturning(AppEntity $app): AbstractExtensionDataProvider
    {
        $provider = static::createStub(AbstractExtensionDataProvider::class);
        $provider->method('getAppEntityFromTechnicalName')->willReturn($app);

        return $provider;
    }

    private function uploadedFile(): UploadedFile
    {
        return new UploadedFile(__FILE__, 'TestApp.zip', 'application/zip', null, true);
    }
}
