<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Framework\ThemeInterface;
use Shopware\Storefront\Theme\Exception\InvalidThemeBundleException;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @var ThemeLifecycleService
     */
    private $themeLifecycleService;

    /**
     * @var StorefrontPluginRegistry
     */
    private $storefrontPluginRegistry;

    /**
     * @var ThemeService
     */
    private $themeService;

    /**
     * @var string
     */
    private $projectDirectory;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(
        ThemeLifecycleService $themeLifecycleService,
        StorefrontPluginRegistry $storefrontPluginRegistry,
        ThemeService $themeService,
        string $projectDirectory,
        EntityRepositoryInterface $themeRepository,
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->themeLifecycleService = $themeLifecycleService;
        $this->storefrontPluginRegistry = $storefrontPluginRegistry;
        $this->themeService = $themeService;
        $this->projectDirectory = $projectDirectory;
        $this->themeRepository = $themeRepository;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            PluginPreActivateEvent::class => 'pluginActivate',
            PluginPreUpdateEvent::class => 'pluginUpdate',
            PluginPreDeactivateEvent::class => 'pluginDeactivateAndUninstall',
            PluginPreUninstallEvent::class => 'pluginDeactivateAndUninstall',
        ];
    }

    public function pluginActivate(PluginPreActivateEvent $event): void
    {
        // create instance of the plugin to create a configuration
        // (the kernel boot is already finished and the activated plugin is missing)
        $storefrontPluginConfig = $this->createConfigFromClassName(
            $event->getPlugin()->getPath(),
            $event->getPlugin()->getBaseClass()
        );
        $context = $event->getContext()->getContext();

        // activate theme if it already exists
        $this->changeThemeActive($event->getPlugin()->getName(), true, $context);

        // add plugin configuration to the list of all active plugin configurations
        $configurationCollection = clone $this->storefrontPluginRegistry->getConfigurations();
        $configurationCollection->add($storefrontPluginConfig);

        // todo refresh without assets and if theme only refresh the new theme?
        $this->refreshAndRecompileIfRequired(
            $storefrontPluginConfig,
            $configurationCollection,
            $context
        );
    }

    public function pluginUpdate(PluginPreUpdateEvent $event): void
    {
        $pluginName = $event->getPlugin()->getName();
        $config = $this->storefrontPluginRegistry->getConfigurations()->getByTechnicalName($pluginName);

        if (!$config) {
            return;
        }

        $this->refreshAndRecompileIfRequired(
            $config,
            $this->storefrontPluginRegistry->getConfigurations(),
            $event->getContext()->getContext()
        );
    }

    /**
     * @param PluginPreDeactivateEvent|PluginPreUninstallEvent $event
     */
    public function pluginDeactivateAndUninstall($event): void
    {
        $pluginName = $event->getPlugin()->getName();
        $config = $this->storefrontPluginRegistry->getConfigurations()->getByTechnicalName($pluginName);

        if (!$config) {
            return;
        }

        if ($config->getIsTheme()) {
            $context = $event->getContext()->getContext();
            // throw an exception if theme is still assigned to a sales channel
            $this->validateThemeAssignment($pluginName, $context);

            // set active = false in the database to theme and all children
            $this->changeThemeActive($pluginName, false, $context);
        }
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

        if (count($themeSalesChannel) === 0 && count($childThemeSalesChannel) === 0) {
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

    private function refreshAndRecompileIfRequired(
        StorefrontPluginConfiguration $config,
        StorefrontPluginConfigurationCollection $configurationCollection,
        Context $context
    ): void {
        if ($config->getIsTheme()) {
            $this->themeLifecycleService->refreshTheme($config, $context);
        }

        if ($config->getStyleFiles()->count() === 0
            && $config->getScriptFiles()->count() === 0) {
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

    private function getSalesChannels(Context $context): SalesChannelCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('themes');

        /** @var SalesChannelCollection $result */
        $result = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        return $result;
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

        if (count($data)) {
            $this->themeRepository->update($data, $context);
        }
    }

    /**
     * @throws ThemeCompileException
     * @throws InvalidThemeBundleException
     */
    private function createConfigFromClassName(string $pluginPath, string $className): StorefrontPluginConfiguration
    {
        /** @var Plugin $plugin */
        $plugin = new $className(true, $pluginPath, $this->projectDirectory);

        if (!$plugin instanceof Plugin) {
            throw new \RuntimeException(
                sprintf('Plugin class "%s" must extend "%s"', \get_class($plugin), Plugin::class)
            );
        }

        if ($plugin instanceof ThemeInterface) {
            return StorefrontPluginConfiguration::createFromConfigFile($plugin);
        }

        return StorefrontPluginConfiguration::createFromBundle($plugin);
    }
}
