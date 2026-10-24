<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopware\Core\Framework\App\Event\AppUploadValidationEvent;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class AppUploadService
{
    public function __construct(
        private readonly AppExtractor $appExtractor,
        private readonly AbstractAppLifecycle $lifecycle,
        private readonly AbstractExtensionDataProvider $extensionDataProvider,
        private readonly SourceResolver $sourceResolver,
        private readonly AppConfirmationDeltaProvider $confirmationDeltaProvider,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Filesystem $filesystem,
        private readonly string $temporaryDirectory,
    ) {
    }

    public function upload(
        UploadedFile $archive,
        Context $context,
        ?string $sourceType = null,
        bool $acceptPermissions = false,
        ?string $expectedAppName = null,
        ?bool $updateOnly = false,
    ): void {
        $temporaryPath = tempnam($this->temporaryDirectory, 'app-upload-');
        if (!\is_string($temporaryPath)) {
            throw AppException::cannotCreateUploadTemporaryFile($this->temporaryDirectory);
        }

        $extractDirectory = $temporaryPath . '-extracted';
        $storedManifest = null;
        $backupPath = null;
        $existingApp = null;
        $previousAppPath = null;

        try {
            $this->filesystem->copy($archive->getPathname(), $temporaryPath, true);
            $appDirectory = $this->appExtractor->extract($temporaryPath, $extractDirectory, $expectedAppName);
            $manifest = Manifest::createFromXmlFile($appDirectory . '/manifest.xml');

            if ($sourceType !== null) {
                $manifest->setSourceType($sourceType);
            } else {
                $manifest->setSourceType($this->sourceResolver->resolveSourceType($manifest));
            }

            $this->eventDispatcher->dispatch(new AppUploadValidationEvent($archive, $manifest, $context));

            try {
                $existingApp = $this->extensionDataProvider->getAppEntityFromTechnicalName($manifest->getMetadata()->getName(), $context);
            } catch (StoreException $exception) {
                if ($exception->getErrorCode() !== StoreException::EXTENSION_NOT_FOUND) {
                    throw $exception;
                }
            }

            if ($existingApp === null && $updateOnly === true) {
                throw AppException::notFound($manifest->getMetadata()->getName());
            }

            if ($existingApp !== null && $updateOnly === false) {
                throw AppException::alreadyInstalled($existingApp->getName());
            }

            $source = $this->sourceResolver->uploadSourceForManifest($manifest);
            if ($existingApp !== null) {
                if (!$acceptPermissions && $this->confirmationDeltaProvider->requiresRenewedConsent($manifest, $existingApp)) {
                    // Preserve the Store API error code consumed by the Administration consent flow.
                    // @phpstan-ignore shopware.domainException
                    throw StoreException::extensionUpdateRequiresConsentAffirmationException(
                        $existingApp->getName(),
                        $this->confirmationDeltaProvider->getReports($manifest, $existingApp)
                    );
                }

                $backupPath = $source->backup($existingApp);
                $previousAppPath = $existingApp->getPath();
            }

            $storedManifest = $manifest;
            $manifest->setPath($source->storeArchive($temporaryPath, $manifest));

            if ($existingApp === null) {
                $this->lifecycle->install($manifest, new AppInstallParameters(activate: false), $context);

                return;
            }

            $this->lifecycle->update(
                $manifest,
                new AppUpdateParameters(acceptPermissions: $acceptPermissions),
                ['id' => $existingApp->getId()],
                $context
            );
        } catch (\Throwable $exception) {
            if ($storedManifest !== null) {
                $source = $this->sourceResolver->uploadSourceForManifest($storedManifest);
                if ($existingApp !== null && $backupPath !== null) {
                    $source->restore($existingApp, $backupPath);
                    try {
                        $previousManifest = Manifest::createFromXmlFile(Path::join($backupPath, 'manifest.xml'));
                        $sourceType = $storedManifest->getSourceType();
                        if ($sourceType !== null) {
                            $previousManifest->setSourceType($sourceType);
                        }
                        $previousManifest->setPath((string) $previousAppPath);
                        $this->lifecycle->update(
                            $previousManifest,
                            new AppUpdateParameters(acceptPermissions: true),
                            ['id' => $existingApp->getId()],
                            $context
                        );
                    } catch (\Throwable) {
                        // If rollback handlers fail too, restored storage can disagree with the app row's attempted version.
                        // Keep the original lifecycle exception as the useful error for the caller.
                    }
                } elseif ($existingApp === null) {
                    $source->removeByManifest($storedManifest);
                }
            }

            throw $exception;
        } finally {
            $this->filesystem->remove([$temporaryPath, $extractDirectory]);
            if ($backupPath !== null) {
                $this->filesystem->remove($backupPath);
            }
        }
    }
}
