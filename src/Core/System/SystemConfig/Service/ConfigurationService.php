<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Service;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
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
     * @throws \InvalidArgumentException
     * @throws BundleConfigNotFoundException
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
                $newField = [
                    'type' => $field['type'],
                    'name' => $domain . $field['name'],
                ];

                unset($field['type'], $field['name']);
                $newField['config'] = $field;
                $card['elements'][$j] = $newField;
            }
            $config[$i] = $card;
        }

        return $config;
    }

    public function checkConfiguration(string $domain): bool
    {
        try {
            $this->getConfiguration($domain);

            return true;
        } catch (\InvalidArgumentException | BundleNotFoundException | BundleConfigNotFoundException $e) {
            return false;
        }
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
