<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\Exception\BundleNotFoundException;
use Shopware\Core\System\SystemConfig\Helper\ConfigReader;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ConfigurationService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $configurationRepository;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var ConfigReader
     */
    private $configReader;

    public function __construct(
        EntityRepositoryInterface $configurationRepository,
        KernelInterface $kernel,
        ConfigReader $configReader
    ) {
        $this->configurationRepository = $configurationRepository;
        $this->kernel = $kernel;
        $this->configReader = $configReader;
    }

    /**
     * @throws BundleNotFoundException
     */
    public function getConfiguration(string $namespace, Context $context, ?string $salesChannelId = null): array
    {
        $bundle = $this->getBundle($namespace);

        if (!$bundle) {
            throw new BundleNotFoundException($namespace);
        }

        $config = $this->configReader->getConfigFromBundle($bundle);

        return $this->patchValuesIntoConfig($config, $namespace, $salesChannelId, $context);
    }

    /**
     * @param array[][] $config
     */
    private function patchValuesIntoConfig(
        array $config,
        string $namespace,
        ?string $salesChannelId,
        Context $context
    ): array {
        $systemConfigCollection = $this->getSystemConfigCollection($namespace, $salesChannelId, $context);

        $configValues = [];
        foreach ($systemConfigCollection as $systemConfig) {
            $configValues[$systemConfig->getConfigurationKey()] = $systemConfig->getConfigurationValue();
        }

        if (!$configValues) {
            return $config;
        }

        foreach ($config as &$card) {
            foreach ($card['fields'] as &$field) {
                if ($systemConfigCollection->fieldNameInCollection($field['name'])) {
                    $field['value'] = $configValues[$field['name']];
                }
            }
            unset($field);
        }
        unset($card);

        return $config;
    }

    private function getSystemConfigCollection(
        string $namespace,
        ?string $salesChannelId,
        Context $context
    ): SystemConfigCollection {
        $criteria = new Criteria([]);

        $criteria->addFilter(
            new EqualsFilter('system_config.namespace', $namespace),
            new EqualsFilter('system_config.salesChannelId', $salesChannelId)
        );

        /** @var SystemConfigCollection $configurations */
        $configurations = $this->configurationRepository->search($criteria, $context)->getEntities();

        return $configurations;
    }

    private function getBundle(string $namespace): ?BundleInterface
    {
        foreach ($this->kernel->getBundles() as $activeBundle) {
            if ($activeBundle->getNamespace() === $namespace) {
                return $activeBundle;
            }
        }

        return null;
    }
}
