<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
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
    private $salesChannelRepository;

    public function __construct(
        ThemeLifecycleService $themeLifecycleService,
        StorefrontPluginRegistry $storefrontPluginRegistry,
        ThemeService $themeService,
        string $projectDirectory,
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->themeLifecycleService = $themeLifecycleService;
        $this->storefrontPluginRegistry = $storefrontPluginRegistry;
        $this->themeService = $themeService;
        $this->projectDirectory = $projectDirectory;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            PluginPreActivateEvent::class => 'pluginActivate',
            PluginPostDeactivateEvent::class => 'pluginDeactivate',
        ];
    }

    public function pluginActivate(PluginPreActivateEvent $event): void
    {
        $className = $event->getPlugin()->getBaseClass();
        /** @var Plugin $plugin */
        $plugin = new $className(true, $this->projectDirectory . '/' . $event->getPlugin()->getPath());

        if (!$plugin instanceof Plugin) {
            throw new \RuntimeException(
                sprintf('Plugin class "%s" must extend "%s"', \get_class($plugin), Plugin::class)
            );
        }

        $storefrontPluginConfig = StorefrontPluginConfiguration::createFromBundle($plugin);
        $configurationCollection = clone $this->storefrontPluginRegistry->getConfigurations();
        $configurationCollection->add($storefrontPluginConfig);

        $this->refreshThemes(
            $storefrontPluginConfig,
            $configurationCollection,
            $event->getContext()->getContext()
        );
    }

    public function pluginDeactivate(PluginPostDeactivateEvent $event): void
    {
        $pluginName = $event->getPlugin()->getName();
        $configurationCollection = $this->storefrontPluginRegistry->getConfigurations()->filter(
            function (StorefrontPluginConfiguration $configuration) use ($pluginName) {
                return $configuration->getTechnicalName() !== $pluginName;
            }
        );

        $storeFrontPluginConfiguration = $this->storefrontPluginRegistry->getConfigurations()->getByTechnicalName($event->getPlugin()->getName());

        if ($storeFrontPluginConfiguration !== null) {
            $this->refreshThemes(
                $storeFrontPluginConfiguration,
                $configurationCollection,
                $event->getContext()->getContext()
            );
        }
    }

    private function refreshThemes(
        StorefrontPluginConfiguration $storefrontPluginConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        Context $context
    ) {
        if ($storefrontPluginConfig->getIsTheme()) {
            $this->themeLifecycleService->refreshThemes($context);
        }

        if (!$storefrontPluginConfig->getStyleFiles() && !$storefrontPluginConfig->getScriptFiles()) {
            return;
        }

        $salesChannels = $this->getSalesChannels($context);

        foreach ($salesChannels as $salesChannel) {
            /** @var ThemeCollection|null $themes */
            $themes = $salesChannel->getExtensionOfType('themes', ThemeCollection::class);
            if (!$themes || !$theme = $themes->first()) {
                continue;
            }

            $this->themeService->compileTheme($salesChannel->getId(), $theme->getId(), $context, $configurationCollection);
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
}
