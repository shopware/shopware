<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\HttpKernel\KernelInterface;
use function Flag\next10286;

/**
 * @Decoratable
 */
class StorefrontPluginRegistry implements StorefrontPluginRegistryInterface
{
    public const BASE_THEME_NAME = 'Storefront';

    /**
     * @var StorefrontPluginConfigurationCollection|null
     */
    private $pluginConfigurations;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var AbstractStorefrontPluginConfigurationFactory
     */
    private $pluginConfigurationFactory;

    /**
     * @var EntityRepositoryInterface|null
     */
    private $appRepository;

    public function __construct(
        KernelInterface $kernel,
        AbstractStorefrontPluginConfigurationFactory $pluginConfigurationFactory,
        ?EntityRepositoryInterface $appRepository
    ) {
        $this->kernel = $kernel;
        $this->pluginConfigurationFactory = $pluginConfigurationFactory;
        $this->appRepository = $appRepository;
    }

    public function getConfigurations(): StorefrontPluginConfigurationCollection
    {
        if ($this->pluginConfigurations) {
            return $this->pluginConfigurations;
        }

        $this->pluginConfigurations = new StorefrontPluginConfigurationCollection();

        $this->addPluginConfigs();
        // remove nullable prop and on-invalid=null behaviour in service declaration
        // when removing the feature flag
        if ($this->appRepository && next10286()) {
            $this->addAppConfigs();
        }

        return $this->pluginConfigurations;
    }

    private function addPluginConfigs(): void
    {
        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $config = $this->pluginConfigurationFactory->createFromBundle($bundle);

            if (!$config->getIsTheme() && !$config->hasFilesToCompile()) {
                continue;
            }

            $this->pluginConfigurations->add($config);
        }
    }

    private function addAppConfigs(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        $apps = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities();
        /** @var AppEntity $app */
        foreach ($apps as $app) {
            $config = $this->pluginConfigurationFactory->createFromApp($app);

            if (!$config->getIsTheme() && !$config->hasFilesToCompile()) {
                continue;
            }

            $this->pluginConfigurations->add($config);
        }
    }
}
