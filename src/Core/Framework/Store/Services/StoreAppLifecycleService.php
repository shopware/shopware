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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\ThemeCollection;

/**
 * @internal - only for use by the app-system
 */
#[Package('checkout')]
class StoreAppLifecycleService extends AbstractStoreAppLifecycleService
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param ?EntityRepository<ThemeCollection> $themeRepository
     *
     * @phpstan-ignore phpat.restrictNamespacesInCore (Storefront dependency is nullable. Don't do that! Will be fixed with https://github.com/shopware/shopware/issues/12966)
     */
    public function __construct(
        private readonly StoreClient $storeClient,
        private readonly AppLoader $appLoader,
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly AppStorage $appStorage,
        private readonly EntityRepository $salesChannelRepository,
        private readonly ?EntityRepository $themeRepository,
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

    private function getThemeIdByTechnicalName(string $technicalName, Context $context): ?string
    {
        if (!$this->themeRepository instanceof EntityRepository) {
            return null;
        }

        return $this->themeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName)),
            $context
        )->firstId();
    }

    private function validateExtensionCanBeRemoved(string $technicalName, string $id, Context $context): void
    {
        $themeId = $this->getThemeIdByTechnicalName($technicalName, $context);

        if ($themeId === null) {
            // extension is not a theme
            return;
        }

        $criteria = new Criteria();
        $criteria->addAggregation(
            new FilterAggregation(
                'assigned_theme_filter',
                new TermsAggregation('assigned_theme', 'themes.id'),
                [new EqualsFilter('themes.id', $themeId)]
            )
        );
        $criteria->addAggregation(
            new FilterAggregation(
                'assigned_children_filter',
                new TermsAggregation('assigned_children', 'themes.parentThemeId'),
                [new EqualsFilter('themes.parentThemeId', $themeId)]
            )
        );

        $aggregates = $this->salesChannelRepository->aggregate($criteria, $context);

        /** @var TermsResult $directlyAssigned */
        $directlyAssigned = $aggregates->get('assigned_theme');

        /** @var TermsResult $assignedChildren */
        $assignedChildren = $aggregates->get('assigned_children');

        if ($directlyAssigned->getKeys() !== [] || $assignedChildren->getKeys() !== []) {
            throw StoreException::extensionThemeStillInUse($id);
        }
    }
}
