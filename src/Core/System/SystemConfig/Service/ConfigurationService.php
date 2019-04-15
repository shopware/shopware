<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Service;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\System\SystemConfig\Exception\BundleNotFoundException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ConfigurationService
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var ConfigReader
     */
    private $configReader;

    public function __construct(KernelInterface $kernel, ConfigReader $configReader)
    {
        $this->kernel = $kernel;
        $this->configReader = $configReader;
    }

    /**
     * @throws BundleNotFoundException
     */
    public function getConfiguration(string $domain): array
    {
        $parts = explode('.', $domain, 2);
        if (count($parts) === 1 && $parts[0] !== '') {
            $scope = 'bundle';
            [$bundleName] = $parts;
        } elseif (count($parts) === 2) {
            [$scope, $bundleName] = $parts;
        } else {
            throw new \InvalidArgumentException('Expected domain');
        }

        $bundle = $this->getBundle($bundleName);

        if (!($bundle instanceof Bundle)) {
            throw new BundleNotFoundException($bundleName);
        }

        // TODO: NEXT-2809 - allow custom config loading
        $config = $this->configReader->getConfigFromBundle($bundle);
        $domain = $scope . '.' . $bundle->getName() . '.';

        foreach ($config as $i => $card) {
            foreach ($card['elements'] as $j => $field) {
                $field['name'] = $domain . $field['name'];
                $card['elements'][$j] = $field;
            }
            $config[$i] = $card;
        }

        return $config;
    }

    private function getBundle(string $bundleName): ?BundleInterface
    {
        $class = array_slice(explode('\\', $bundleName), -1)[0];
        foreach ($this->kernel->getBundles() as $bundle) {
            if ($bundle->getName() === $class) {
                return $bundle;
            }
        }

        return null;
    }
}
