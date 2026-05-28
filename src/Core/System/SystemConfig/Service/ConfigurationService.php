<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigCard;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigElement;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigTab;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

#[Package('framework')]
class ConfigurationService
{
    /**
     * @internal
     *
     * @param BundleInterface[] $bundles
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly iterable $bundles,
        private readonly ConfigReader $configReader,
        private readonly AppConfigReader $appConfigReader,
        private readonly EntityRepository $appRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws SystemConfigException
     * @throws \InvalidArgumentException
     * @throws BundleConfigNotFoundException
     * @throws UtilException when config.xml exists but contains invalid XML
     *
     * @return list<SystemConfigTab>
     */
    public function getSystemConfiguration(string $domain, Context $context): array
    {
        $validDomain = preg_match('/^([\w-]+)\.?([\w-]*)$/', $domain, $match);

        if (!$validDomain) {
            throw SystemConfigException::invalidDomain();
        }

        $scope = $match[1];
        $configName = $match[2] !== '' ? $match[2] : null;

        $config = $this->fetchConfiguration($scope === 'core' ? 'System' : $scope, $configName, $context);
        if (!$config) {
            throw SystemConfigException::configurationNotFound($scope);
        }

        $domain = rtrim($domain, '.') . '.';

        $tabs = [];

        foreach ($config as $tab) {
            $cards = [];

            foreach ($tab['cards'] ?? [] as $card) {
                if (\array_key_exists('flag', $card) && !Feature::isActive($card['flag'])) {
                    continue;
                }

                $elements = [];

                foreach ($card['elements'] ?? [] as $element) {
                    if (\array_key_exists('flag', $element) && !Feature::isActive($element['flag'])) {
                        continue;
                    }

                    $config = $element;

                    unset($config['name'], $config['type']);

                    $elements[] = new SystemConfigElement(
                        $domain . $element['name'],
                        $config,
                        $element['type'] ?? null
                    );
                }

                $cards[] = new SystemConfigCard(
                    $elements,
                    $card['title'] ?? [],
                    $card['name'] ?? null
                );
            }

            $tabs[] = new SystemConfigTab(
                $cards,
                $tab['title'] ?? null,
                $tab['name'] ?? null
            );
        }

        return $tabs;
    }

    /**
     * @return list<SystemConfigTab>
     */
    public function getResolvedSystemConfiguration(string $domain, Context $context, ?string $salesChannelId = null): array
    {
        $config = [];

        if ($this->checkSystemConfiguration($domain, $context)) {
            $config = array_merge(
                $config,
                $this->enrichSystemConfigValues(
                    $this->getSystemConfiguration($domain, $context),
                    $salesChannelId
                )
            );
        }

        return $config;
    }

    public function checkSystemConfiguration(string $domain, Context $context): bool
    {
        try {
            $this->getSystemConfiguration($domain, $context);

            return true;
        } catch (\InvalidArgumentException|SystemConfigException|BundleConfigNotFoundException|UtilException $e) {
            $this->logConfigurationException($domain, $e);

            return false;
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use getSystemConfiguration() instead
     *
     * @throws SystemConfigException
     * @throws \InvalidArgumentException
     * @throws BundleConfigNotFoundException
     * @throws UtilException when config.xml exists but contains invalid XML
     *
     * @return array<mixed>
     */
    public function getConfiguration(string $domain, Context $context): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getConfiguration', 'v6.8.0.0', 'getSystemConfiguration')
        );

        $validDomain = preg_match('/^([\w-]+)\.?([\w-]*)$/', $domain, $match);

        if (!$validDomain) {
            throw SystemConfigException::invalidDomain();
        }

        $scope = $match[1];
        $configName = $match[2] !== '' ? $match[2] : null;

        $config = $this->fetchConfiguration($scope === 'core' ? 'System' : $scope, $configName, $context);
        if (!$config) {
            throw SystemConfigException::configurationNotFound($scope);
        }

        $domain = rtrim($domain, '.') . '.';

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('SYSTEM_CONFIG_TABS')) {
            // collect all cards from the tabs into the config array to keep the old structure
            $config = array_values(array_reduce($config, fn (array $carry, array $tab) => [
                ...$carry,
                ...($tab['cards'] ?? []),
            ], []));
        }

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

            if (isset($card['elements']) && \is_array($card['elements'])) {
                $card['elements'] = array_values($card['elements']);
            }

            $config[$i] = $card;
        }

        return array_values($config);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use getResolvedSystemConfiguration() instead
     *
     * @return array<mixed>
     */
    public function getResolvedConfiguration(string $domain, Context $context, ?string $salesChannelId = null): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getResolvedConfiguration', 'v6.8.0.0', 'getResolvedSystemConfiguration')
        );

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

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use checkSystemConfiguration() instead
     */
    public function checkConfiguration(string $domain, Context $context): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'checkConfiguration', 'v6.8.0.0', 'checkSystemConfiguration')
        );

        try {
            $this->getConfiguration($domain, $context);

            return true;
        } catch (\InvalidArgumentException|SystemConfigException|BundleConfigNotFoundException|UtilException $e) {
            $this->logConfigurationException($domain, $e);

            return false;
        }
    }

    private function logConfigurationException(string $domain, \Throwable $e): void
    {
        $context = [
            'domain' => $domain,
            'message' => $e->getMessage(),
            'exception' => $e,
        ];

        match (true) {
            $e instanceof \InvalidArgumentException => $this->logger->debug(
                'Invalid configuration domain "{domain}": {message}',
                $context
            ),
            $e instanceof BundleConfigNotFoundException => $this->logger->debug(
                'No configuration file found for "{domain}": {message}',
                $context
            ),
            $e instanceof SystemConfigException => $this->logger->debug(
                'Configuration not loaded for "{domain}" (plugin/app not installed or not activated): {message}',
                $context
            ),
            // UtilException (XML parsing errors) and any other unexpected exceptions
            default => $this->logger->error(
                'Failed to parse configuration for "{domain}": {message}',
                $context
            ),
        };
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

        return $app ? $this->appConfigReader->read($app) : null;
    }

    private function getAppByName(string $name, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var AppEntity|null $result */
        $result = $this->appRepository->search($criteria, $context)->getEntities()->first();

        return $result;
    }

    /**
     * @param list<SystemConfigTab> $config
     *
     * @return list<SystemConfigTab>
     */
    private function enrichSystemConfigValues(array $config, ?string $salesChannelId): array
    {
        foreach ($config as $tab) {
            foreach ($tab->cards as $card) {
                foreach ($card->elements as $element) {
                    $element->value = $this->systemConfigService->get(
                        $element->name,
                        $salesChannelId
                    ) ?? $element->config['defaultValue'] ?? '';
                }
            }
        }

        return $config;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use enrichSystemConfigValues() instead
     *
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
