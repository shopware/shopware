<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\StoreException;

/**
 * @internal - only for use by the app-system
 */
#[Package('checkout')]
class StoreAppLifecycleService extends AbstractStoreAppLifecycleService
{
    /**
     * @param iterable<ExtensionRemovalValidatorInterface> $removalValidators
     */
    public function __construct(
        private readonly StoreClient $storeClient,
        private readonly AppLoader $appLoader,
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly AppStorage $appStorage,
        private readonly iterable $removalValidators,
        private readonly AppConfirmationDeltaProvider $appDeltaService
    ) {
    }

    public function installExtension(string $technicalName, Context $context): void
    {
        $manifests = $this->appLoader->load();

        if (!isset($manifests[$technicalName])) {
            throw StoreException::extensionInstallException(\sprintf('Cannot find app by name %s', $technicalName));
        }

        $this->appLifecycle->install($manifests[$technicalName], new AppInstallParameters(activate: false), $context);
    }

    public function uninstallExtension(string $technicalName, Context $context, bool $keepUserData = false): void
    {
        try {
            $app = $this->getAppByName($technicalName, $context);
        } catch (ExtensionNotFoundException) {
            // extension is not installed, so we can skip the uninstall process
            return;
        }

        $this->validateExtensionCanBeRemoved($technicalName, $app->getId(), $context);
        $this->appLifecycle->uninstall($technicalName, ['id' => $app->getId()], $context, $keepUserData);
    }

    public function removeExtensionAndCancelSubscription(int $licenseId, string $technicalName, string $id, bool $keepUserData, Context $context): void
    {
        $this->validateExtensionCanBeRemoved($technicalName, $id, $context);
        $app = $this->appStorage->findById($id, $context);
        if (!$app) {
            throw StoreException::extensionNotFoundFromId($id);
        }

        $this->storeClient->cancelSubscription($licenseId, $context);
        $this->appLifecycle->uninstall($technicalName, ['id' => $id], $context);
        $this->deleteExtension($technicalName, $keepUserData, $context);
    }

    public function deleteExtension(string $technicalName, bool $keepUserData, Context $context): void
    {
        $this->uninstallExtension($technicalName, $context, $keepUserData);
        $this->appLoader->deleteApp($technicalName);
    }

    public function activateExtension(string $technicalName, Context $context): void
    {
        $id = $this->getAppByName($technicalName, $context)->getId();
        $this->appLifecycle->activate($id, $context);
    }

    public function deactivateExtension(string $technicalName, Context $context): void
    {
        $id = $this->getAppByName($technicalName, $context)->getId();
        $this->appLifecycle->deactivate($id, $context);
    }

    public function updateExtension(string $technicalName, bool $allowNewPermissions, Context $context): void
    {
        $manifests = $this->appLoader->load();

        if (!\array_key_exists($technicalName, $manifests)) {
            throw StoreException::extensionInstallException('Cannot find extension');
        }

        $app = $this->getAppByName($technicalName, $context);
        $requiresRenewedConsent = $this->appDeltaService->requiresRenewedConsent(
            $manifests[$technicalName],
            $app
        );

        if (!$allowNewPermissions && $requiresRenewedConsent) {
            $deltas = $this->appDeltaService->getReports(
                $manifests[$technicalName],
                $app
            );

            throw StoreException::extensionUpdateRequiresConsentAffirmationException($technicalName, $deltas);
        }

        $this->appLifecycle->update(
            $manifests[$technicalName],
            new AppUpdateParameters(),
            ['id' => $app->getId()],
            $context
        );
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getDecorated(): AbstractStoreAppLifecycleService
    {
        throw new DecorationPatternException(self::class);
    }

    private function getAppByName(string $technicalName, Context $context): AppEntity
    {
        $app = $this->appStorage->findByName($technicalName, $context);

        if (!$app) {
            throw StoreException::extensionNotFoundFromTechnicalName($technicalName);
        }

        return $app;
    }

    private function validateExtensionCanBeRemoved(string $technicalName, string $id, Context $context): void
    {
        foreach ($this->removalValidators as $validator) {
            $validator->validateCanBeRemoved($technicalName, $id, $context);
        }
    }
}
