<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Service;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\ConfigurationNotFoundException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class ConfigurationService
{
    /**
     * @var array
     */
    private $bundles;

    /**
     * @var ConfigReader
     */
    private $configReader;

    /**
     * @var AbstractAppLoader
     */
    private $appLoader;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @param BundleInterface[] $bundles
     */
    public function __construct(
        iterable $bundles,
        ConfigReader $configReader,
        AbstractAppLoader $appLoader,
        EntityRepositoryInterface $appRepository
    ) {
        $this->bundles = $bundles;
        $this->configReader = $configReader;
        $this->appLoader = $appLoader;
        $this->appRepository = $appRepository;
    }

    /**
     * @throws ConfigurationNotFoundException
     * @throws \InvalidArgumentException
     * @throws BundleConfigNotFoundException
     *
     * @deprecated tag:v6.4.0 $context param will be required
     */
    public function getConfiguration(string $domain, ?Context $context = null): array
    {
        if (!$context) {
            $context = Context::createDefaultContext();
        }

        $validDomain = preg_match('/^([\w-]+)\.?([\w-]*)$/', $domain, $match);

        if (!$validDomain) {
            throw new \InvalidArgumentException('Expected domain');
        }

        $scope = $match[1];
        $configName = $match[2] !== '' ? $match[2] : null;

        $config = $this->fetchConfiguration($scope === 'core' ? 'System' : $scope, $configName, $context);
        if (!$config) {
            throw new ConfigurationNotFoundException($scope);
        }

        $domain = rtrim($domain, '.') . '.';

        foreach ($config as $i => $card) {
            if (\array_key_exists('flag', $card) && !Feature::isActive($card['flag'])) {
                unset($config[$i]);

                continue;
            }

            foreach ($card['elements'] as $j => $field) {
                $newField = ['name' => $domain . $field['name']];

                if (\array_key_exists('flag', $field) && !Feature::isActive($field['flag'])) {
                    unset($card['elements'][$j]);

                    continue;
                }

                if (\array_key_exists('type', $field)) {
                    $newField['type'] = $field['type'];
                }

                unset($field['type'], $field['name']);
                if ($field === []) {
                    $field = new \stdClass();
                }
                $newField['config'] = $field;
                $card['elements'][$j] = $newField;
            }
            $config[$i] = $card;
        }

        return $config;
    }

    /**
     * @deprecated tag:v6.4.0 $context param will be required
     */
    public function checkConfiguration(string $domain, ?Context $context = null): bool
    {
        try {
            $this->getConfiguration($domain, $context);

            return true;
        } catch (\InvalidArgumentException | ConfigurationNotFoundException | BundleConfigNotFoundException $e) {
            return false;
        }
    }

    private function fetchConfiguration(string $scope, ?string $configName, Context $context): ?array
    {
        $technicalName = \array_slice(explode('\\', $scope), -1)[0];
        foreach ($this->bundles as $bundle) {
            if ($bundle->getName() === $technicalName) {
                return $this->configReader->getConfigFromBundle($bundle, $configName);
            }
        }

        $app = $this->getAppByName($technicalName, $context);
        if ($app) {
            return $this->appLoader->getConfiguration($app);
        }

        return null;
    }

    private function getAppByName(string $name, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var AppEntity|null $result */
        $result = $this->appRepository->search($criteria, $context)->first();

        return $result;
    }
}
