<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Store\Exception\ExtensionInstallException;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\Exception\ExtensionThemeStillInUseException;
use Shopware\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - This class will be marked as "internal - only for use by the app-system"
 */
class StoreAppLifecycleService extends AbstractStoreAppLifecycleService
{
    private StoreClient $storeClient;

    private AbstractAppLifecycle $appLifecycle;

    private EntityRepositoryInterface $appRepository;

    private EntityRepositoryInterface $salesChannelRepository;

    private ?EntityRepositoryInterface $themeRepository;

    private AppStateService $appStateService;

    private AbstractAppLoader $appLoader;

    private AppConfirmationDeltaProvider $appDeltaService;

    /**
     * @internal
     */
    public function __construct(
        StoreClient $storeClient,
        AbstractAppLoader $appLoader,
        AbstractAppLifecycle $appLifecycle,
        EntityRepositoryInterface $appRepository,
        EntityRepositoryInterface $salesChannelRepository,
        ?EntityRepositoryInterface $themeRepository,
        AppStateService $appStateService,
        AppConfirmationDeltaProvider $appDeltaService
    ) {
        $this->storeClient = $storeClient;
        $this->appLifecycle = $appLifecycle;
        $this->appRepository = $appRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->themeRepository = $themeRepository;
        $this->appStateService = $appStateService;
        $this->appLoader = $appLoader;
        $this->appDeltaService = $appDeltaService;
    }

    public function installExtension(string $technicalName, Context $context): void
    {
        $manifests = $this->appLoader->load();

        if (!isset($manifests[$technicalName])) {
            throw new ExtensionInstallException(sprintf('Cannot find app by name %s', $technicalName));
        }

        $this->appLifecycle->install($manifests[$technicalName], false, $context);
    }

    public function uninstallExtension(string $technicalName, Context $context, bool $keepUserData = false): void
    {
        try {
            $app = $this->getAppByName($technicalName, $context);
        } catch (ExtensionNotFoundException $e) {
            return;
        }

        $this->validateExtensionCanBeRemoved($technicalName, $app->getId(), $context);
        $this->appLifecycle->delete($technicalName, ['id' => $app->getId(), 'roleId' => $app->getAclRoleId()], $context, $keepUserData);
    }

    public function removeExtensionAndCancelSubscription(int $licenseId, string $technicalName, string $id, Context $context): void
    {
        $this->validateExtensionCanBeRemoved($technicalName, $id, $context);
        $app = $this->getAppById($id, $context);
        $this->storeClient->cancelSubscription($licenseId, $context);
        $this->appLifecycle->delete($technicalName, ['id' => $id, 'roleId' => $app->getAclRoleId()], $context);
        $this->deleteExtension($technicalName);
    }

    public function deleteExtension(string $technicalName): void
    {
        $this->appLoader->deleteApp($technicalName);
    }

    public function activateExtension(string $technicalName, Context $context): void
    {
        $id = $this->getAppByName($technicalName, $context)->getId();
        $this->appStateService->activateApp($id, $context);
    }

    public function deactivateExtension(string $technicalName, Context $context): void
    {
        $id = $this->getAppByName($technicalName, $context)->getId();
        $this->appStateService->deactivateApp($id, $context);
    }

    public function updateExtension(string $technicalName, bool $allowNewPermissions, Context $context): void
    {
        $manifests = $this->appLoader->load();

        if (!\array_key_exists($technicalName, $manifests)) {
            throw new ExtensionInstallException('Cannot find extension');
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

            throw ExtensionUpdateRequiresConsentAffirmationException::fromDelta($technicalName, $deltas);
        }

        $this->appLifecycle->update(
            $manifests[$technicalName],
            [
                'id' => $app->getId(),
                'version' => $app->getVersion(),
                'roleId' => $app->getAclRoleId(),
            ],
            $context
        );
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed
     */
    public function getAppIdByName(string $technicalName, Context $context): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $criteria = (new Criteria())->addFilter(new EqualsFilter('name', $technicalName));
        $app = $this->appRepository->searchIds($criteria, $context)->firstId();

        if ($app === null) {
            throw ExtensionNotFoundException::fromTechnicalName($technicalName);
        }

        return $app;
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
        $criteria = (new Criteria())->addFilter(new EqualsFilter('name', $technicalName));
        $app = $this->appRepository->search($criteria, $context)->first();

        if ($app === null) {
            throw ExtensionNotFoundException::fromTechnicalName($technicalName);
        }

        return $app;
    }

    private function getThemeIdByTechnicalName(string $technicalName, Context $context): ?string
    {
        if (!$this->themeRepository instanceof EntityRepositoryInterface) {
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
            //extension is not a theme
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

        if (!empty($directlyAssigned->getKeys()) || !empty($assignedChildren->getKeys())) {
            throw new ExtensionThemeStillInUseException($id);
        }
    }

    private function getAppById(string $id, Context $context): AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $id));

        /** @var AppEntity $app */
        $app = $this->appRepository->search($criteria, $context)->first();

        return $app;
    }
}
