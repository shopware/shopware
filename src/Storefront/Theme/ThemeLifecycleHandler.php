<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

class ThemeLifecycleHandler
{
    /**
     * @var ThemeLifecycleService
     */
    private $themeLifecycleService;

    /**
     * @var ThemeService
     */
    private $themeService;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var StorefrontPluginRegistryInterface
     */
    private $storefrontPluginRegistry;

    public function __construct(
        ThemeLifecycleService $themeLifecycleService,
        ThemeService $themeService,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $themeRepository,
        StorefrontPluginRegistryInterface $storefrontPluginRegistry
    ) {
        $this->themeLifecycleService = $themeLifecycleService;
        $this->themeService = $themeService;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->themeRepository = $themeRepository;
        $this->storefrontPluginRegistry = $storefrontPluginRegistry;
    }

    public function handleThemeInstallOrUpdate(
        StorefrontPluginConfiguration $config,
        StorefrontPluginConfigurationCollection $configurationCollection,
        Context $context
    ): void {
        if ($config->getIsTheme()) {
            $this->themeLifecycleService->refreshTheme($config, $context);
            $this->changeThemeActive($config->getTechnicalName(), true, $context);
        }

        $this->recompileThemesIfNecessary($config, $context, $configurationCollection);
    }

    public function handleThemeUninstall(StorefrontPluginConfiguration $config, Context $context): void
    {
        if ($config->getIsTheme()) {
            // throw an exception if theme is still assigned to a sales channel
            $this->validateThemeAssignment($config->getTechnicalName(), $context);

            // set active = false in the database to theme and all children
            $this->changeThemeActive($config->getTechnicalName(), false, $context);
        }

        $configs = $this->storefrontPluginRegistry->getConfigurations();
        $configs = $configs->filter(function (StorefrontPluginConfiguration $registeredConfig) use ($config): bool {
            return $registeredConfig->getTechnicalName() !== $config->getTechnicalName();
        });

        $this->recompileThemesIfNecessary($config, $context, $configs);
    }

    /**
     * @throws ThemeAssignmentException
     * @throws InconsistentCriteriaIdsException
     */
    private function validateThemeAssignment(string $technicalName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannels');
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($criteria, $context)->first();

        if (!$theme) {
            return;
        }

        $themeSalesChannel = [];
        if ($theme->getSalesChannels() && $theme->getSalesChannels()->count() > 0) {
            $themeSalesChannel[$technicalName] = $this->getSalesChannelNames($theme->getSalesChannels());
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentThemeId', $theme->getId()));
        $criteria->addAssociation('salesChannels');
        /** @var ThemeCollection|null $childThemes */
        $childThemes = $this->themeRepository->search($criteria, $context);

        $childThemeSalesChannel = [];
        if ($childThemes && $childThemes->count() > 0) {
            foreach ($childThemes as $childTheme) {
                if (!$childTheme->getSalesChannels() || $childTheme->getSalesChannels()->count() === 0) {
                    continue;
                }
                $childThemeSalesChannel[$childTheme->getName()] = $this->getSalesChannelNames($childTheme->getSalesChannels());
            }
        }

        if (\count($themeSalesChannel) === 0 && \count($childThemeSalesChannel) === 0) {
            return;
        }

        throw new ThemeAssignmentException($technicalName, $themeSalesChannel, $childThemeSalesChannel);
    }

    private function getSalesChannelNames(SalesChannelCollection $salesChannels): array
    {
        $names = [];
        foreach ($salesChannels as $salesChannel) {
            $names[] = $salesChannel->getName();
        }

        return $names;
    }

    private function changeThemeActive(string $technicalName, bool $active, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->addAssociation('childThemes');
        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($criteria, $context)->first();

        if (!$theme) {
            return;
        }

        $data = [];
        $data[] = ['id' => $theme->getId(), 'active' => $active];
        if ($theme->getChildThemes()) {
            foreach ($theme->getChildThemes()->getIds() as $id) {
                $data[] = ['id' => $id, 'active' => $active];
            }
        }

        if (\count($data)) {
            $this->themeRepository->update($data, $context);
        }
    }

    private function getSalesChannels(Context $context): SalesChannelCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('themes');

        /** @var SalesChannelCollection $result */
        $result = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        return $result;
    }

    private function recompileThemesIfNecessary(StorefrontPluginConfiguration $config, Context $context, StorefrontPluginConfigurationCollection $configurationCollection): void
    {
        if (!$config->hasFilesToCompile()) {
            return;
        }

        $salesChannels = $this->getSalesChannels($context);

        foreach ($salesChannels as $salesChannel) {
            /** @var ThemeCollection|null $themes */
            $themes = $salesChannel->getExtensionOfType('themes', ThemeCollection::class);
            if (!$themes || !$theme = $themes->first()) {
                continue;
            }

            $this->themeService->compileTheme(
                $salesChannel->getId(),
                $theme->getId(),
                $context,
                $configurationCollection
            );
        }
    }
}
