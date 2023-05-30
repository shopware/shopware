<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Service;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\ConfigurationNotFoundException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

#[Package('system-settings')]
class ConfigurationService
{
    /**
     * @internal
     *
     * @param BundleInterface[] $bundles
     */
    public function __construct(
        private readonly iterable $bundles,
        private readonly ConfigReader $configReader,
        private readonly AbstractAppLoader $appLoader,
        private readonly EntityRepository $appRepository,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    /**
     * @throws ConfigurationNotFoundException
     * @throws \InvalidArgumentException
     * @throws BundleConfigNotFoundException
     *
     * @return array<mixed>
     */
    public function getConfiguration(string $domain, Context $context): array
    {
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

            foreach ($card['elements'] ?? [] as $j => $field) {
                $newField = ['name' => $domain . $field['name']];

                if (\array_key_exists('flag', $field) && !Feature::isActive($field['flag'])) {
                    unset($card['elements'][$j]);

                    continue;
                }

                if (\array_key_exists('type', $field)) {
                    $newField['type'] = $field['type'];
                }

                unset($field['type'], $field['name']);
                $newField['config'] = $field;
                $card['elements'][$j] = $newField;
            }
            $config[$i] = $card;
        }

        return $config;
    }

    /**
     * @return array<mixed>
     */
    public function getResolvedConfiguration(string $domain, Context $context, ?string $salesChannelId = null): array
    {
        $config = [];
        if ($this->checkConfiguration($domain, $context)) {
            $config = array_merge(
                $config,
                $this->enrichValues(
                    $this->getConfiguration($domain, $context),
                    $salesChannelId
                )
            );
        }

        return $config;
    }

    public function checkConfiguration(string $domain, Context $context): bool
    {
        try {
            $this->getConfiguration($domain, $context);

            return true;
        } catch (\InvalidArgumentException | ConfigurationNotFoundException | BundleConfigNotFoundException) {
            return false;
        }
    }

    /**
     * @return array<mixed>|null
     */
    private function fetchConfiguration(string $scope, ?string $configName, Context $context): ?array
    {
        $technicalName = \array_slice(explode('\\', $scope), -1)[0];

        foreach ($this->bundles as $bundle) {
            if ($bundle->getName() === $technicalName && $bundle instanceof Bundle) {
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

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function enrichValues(array $config, ?string $salesChannelId): array
    {
        foreach ($config as &$card) {
            if (!\is_array($card['elements'] ?? false)) {
                continue;
            }

            foreach ($card['elements'] as &$element) {
                $element['value'] = $this->systemConfigService->get(
                    $element['name'],
                    $salesChannelId
                ) ?? $element['config']['defaultValue'] ?? '';
            }
        }

        return $config;
    }
}
