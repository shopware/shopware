<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Store\Exception\ExtensionInstallException;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\Exception\ExtensionRequiresNewPrivilegesException;
use Shopware\Core\Framework\Store\Exception\ExtensionThemeStillInUseException;

class StoreAppLifecycleService extends AbstractStoreAppLifecycleService
{
    /**
     * @var StoreClient
     */
    private $storeClient;

    /**
     * @var AbstractAppLifecycle
     */
    private $appLifecycle;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var AppStateService
     */
    private $appStateService;

    /**
     * @var AbstractAppLoader
     */
    private $appLoader;

    public function __construct(
        StoreClient $storeClient,
        AbstractAppLoader $appLoader,
        AbstractAppLifecycle $appLifecycle,
        EntityRepositoryInterface $appRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $themeRepository,
        AppStateService $appStateService
    ) {
        $this->storeClient = $storeClient;
        $this->appLifecycle = $appLifecycle;
        $this->appRepository = $appRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->themeRepository = $themeRepository;
        $this->appStateService = $appStateService;
        $this->appLoader = $appLoader;
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
            $id = $this->getAppIdByName($technicalName, $context);
        } catch (ExtensionNotFoundException $e) {
            return;
        }

        $this->validateExtensionCanBeRemoved($technicalName, $id, $context);
        $app = $this->getAppById($id, $context);
        $this->appLifecycle->delete($technicalName, ['id' => $id, 'roleId' => $app->getAclRoleId()], $context, $keepUserData);
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
        $id = $this->getAppIdByName($technicalName, $context);
        $this->appStateService->activateApp($id, $context);
    }

    public function deactivateExtension(string $technicalName, Context $context): void
    {
        $id = $this->getAppIdByName($technicalName, $context);
        $this->appStateService->deactivateApp($id, $context);
    }

    public function updateExtension(string $technicalName, bool $allowNewPrivileges, Context $context): void
    {
        $manifests = $this->appLoader->load();

        if (!\array_key_exists($technicalName, $manifests)) {
            throw new ExtensionInstallException('Cannot find extension');
        }

        $id = $this->getAppIdByName($technicalName, $context);
        $app = $this->getAppById($id, $context);
        $newPrivileges = $this->diffPrivileges($manifests[$technicalName], $app);

        if (!$allowNewPrivileges && \count($newPrivileges) > 0) {
            throw ExtensionRequiresNewPrivilegesException::fromPrivilegeList($technicalName, $newPrivileges);
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

    public function getAppIdByName(string $technicalName, Context $context): string
    {
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

    private function diffPrivileges(Manifest $manifest, AppEntity $app): array
    {
        $permissions = $manifest->getPermissions();
        if (!$permissions) {
            return [];
        }
        $newPrivileges = $permissions->asParsedPrivileges();

        $aclRole = $app->getAclRole();
        if (!$aclRole) {
            return $newPrivileges;
        }
        $currentPrivileges = $aclRole->getPrivileges();

        return array_diff($newPrivileges, $currentPrivileges);
    }

    private function getThemeIdByTechnicalName(string $technicalName, Context $context): ?string
    {
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
