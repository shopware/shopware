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
        $validDomain = preg_match('/^([\w-]+)\.?([\w-]*)$/', $domain, $match);

        if (!$validDomain) {
            throw new \InvalidArgumentException('Expected domain');
        }

        $scope = $match[1];
        $configName = $match[2] !== '' ? $match[2] : null;

        $bundle = $this->getBundle($scope === 'core' ? 'System' : $scope);

        if (!($bundle instanceof Bundle)) {
            throw new BundleNotFoundException($scope);
        }

        $config = $this->configReader->getConfigFromBundle($bundle, $configName);

        $domain = rtrim($domain, '.') . '.';

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
