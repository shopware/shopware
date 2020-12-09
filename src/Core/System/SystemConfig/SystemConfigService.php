<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\InvalidDomainException;
use Shopware\Core\System\SystemConfig\Exception\InvalidKeyException;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Config\Util\XmlUtils;

class SystemConfigService
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $systemConfigRepository;

    /**
     * @var array[]
     */
    private $configs = [];

    /**
     * @var ConfigReader
     */
    private $configReader;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $systemConfigRepository,
        ConfigReader $configReader,
        EntityRepositoryInterface $pluginRepository
    ) {
        $this->connection = $connection;
        $this->systemConfigRepository = $systemConfigRepository;
        $this->configReader = $configReader;
        $this->pluginRepository = $pluginRepository;
    }

    /**
     * @return array|bool|float|int|string|null
     */
    public function get(string $key, ?string $salesChannelId = null)
    {
        $config = $this->load($salesChannelId);

        $parts = explode('.', $key);

        $pointer = $config;

        foreach ($parts as $part) {
            if (!\is_array($pointer)) {
                return null;
            }

            if (\array_key_exists($part, $pointer)) {
                $pointer = $pointer[$part];

                continue;
            }

            return null;
        }

        return $pointer;
    }

    public function getString(string $key, ?string $salesChannelId = null): string
    {
        $value = $this->get($key, $salesChannelId);
        if (!\is_array($value)) {
            return (string) $value;
        }

        throw new InvalidSettingValueException($key, 'string', \gettype($value));
    }

    public function getInt(string $key, ?string $salesChannelId = null): int
    {
        $value = $this->get($key, $salesChannelId);
        if (!\is_array($value)) {
            return (int) $value;
        }

        throw new InvalidSettingValueException($key, 'int', \gettype($value));
    }

    public function getFloat(string $key, ?string $salesChannelId = null): float
    {
        $value = $this->get($key, $salesChannelId);
        if (!\is_array($value)) {
            return (float) $value;
        }

        throw new InvalidSettingValueException($key, 'float', \gettype($value));
    }

    public function getBool(string $key, ?string $salesChannelId = null): bool
    {
        return (bool) $this->get($key, $salesChannelId);
    }

    /**
     * gets all available shop configs and returns them as an array
     */
    public function all(?string $salesChannelId = null): array
    {
        return $this->load($salesChannelId);
    }

    /**
     * @throws InvalidDomainException
     * @throws InvalidUuidException
     * @throws InconsistentCriteriaIdsException
     */
    public function getDomain(string $domain, ?string $salesChannelId = null, bool $inherit = false): array
    {
        $domain = trim($domain);
        if ($domain === '') {
            throw new InvalidDomainException('Empty domain');
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('LOWER(HEX(id))')
            ->from('system_config');

        if ($inherit) {
            $queryBuilder->where('sales_channel_id IS NULL OR sales_channel_id = :salesChannelId');
        } elseif ($salesChannelId === null) {
            $queryBuilder->where('sales_channel_id IS NULL');
        } else {
            $queryBuilder->where('sales_channel_id = :salesChannelId');
        }

        $domain = rtrim($domain, '.') . '.';
        $escapedDomain = str_replace('%', '\\%', $domain);

        $salesChannelId = $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null;

        $queryBuilder->andWhere('configuration_key LIKE :prefix')
            ->orderBy('configuration_key', 'ASC')
            ->addOrderBy('sales_channel_id', 'ASC')
            ->setParameter('prefix', $escapedDomain . '%')
            ->setParameter('salesChannelId', $salesChannelId);
        $ids = $queryBuilder->execute()->fetchAll(FetchMode::COLUMN);

        if (empty($ids)) {
            return [];
        }

        $criteria = new Criteria($ids);
        /** @var SystemConfigCollection $collection */
        $collection = $this->systemConfigRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        $collection->sortByIdArray($ids);
        $merged = [];

        foreach ($collection as $cur) {
            $key = $cur->getConfigurationKey();
            $value = $cur->getConfigurationValue();

            $inheritedValuePresent = \array_key_exists($key, $merged);
            $valueConsideredEmpty = !\is_bool($value) && empty($value);

            if ($inheritedValuePresent && $valueConsideredEmpty) {
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    /**
     * @param array|bool|float|int|string|null $value
     */
    public function set(string $key, $value, ?string $salesChannelId = null): void
    {
        // reset internal cache
        $this->configs = [];

        $key = trim($key);
        $this->validate($key, $salesChannelId);

        $id = $this->getId($key, $salesChannelId);
        if ($value === null) {
            if ($id) {
                $this->systemConfigRepository->delete([['id' => $id]], Context::createDefaultContext());
            }

            return;
        }

        $data = [
            'id' => $id ?? Uuid::randomHex(),
            'configurationKey' => $key,
            'configurationValue' => $value,
            'salesChannelId' => $salesChannelId,
        ];
        $this->systemConfigRepository->upsert([$data], Context::createDefaultContext());
    }

    public function delete(string $key, ?string $salesChannel = null): void
    {
        $this->set($key, null, $salesChannel);
    }

    /**
     * Fetches default values from bundle configuration and saves it to database
     */
    public function savePluginConfiguration(Bundle $bundle, bool $override = false): void
    {
        try {
            $config = $this->configReader->getConfigFromBundle($bundle);
        } catch (BundleConfigNotFoundException $e) {
            return;
        }

        $prefix = $bundle->getName() . '.config.';

        $this->saveConfig($config, $prefix, $override);
    }

    public function saveConfig(array $config, string $prefix, bool $override): void
    {
        foreach ($config as $card) {
            foreach ($card['elements'] as $element) {
                $key = $prefix . $element['name'];
                if (!isset($element['defaultValue'])) {
                    continue;
                }

                $value = XmlUtils::phpize($element['defaultValue']);
                if ($override || $this->get($key) === null) {
                    $this->set($key, $value);
                }
            }
        }
    }

    public function deletePluginConfiguration(Bundle $bundle): void
    {
        try {
            $config = $this->configReader->getConfigFromBundle($bundle);
        } catch (BundleConfigNotFoundException $e) {
            return;
        }

        $prefix = $bundle->getName() . '.config.';

        $configKeys = [];
        foreach ($config as $card) {
            foreach ($card['elements'] as $element) {
                $configKeys[] = $prefix . $element['name'];
            }
        }

        if (empty($configKeys)) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('configurationKey', $configKeys));
        $systemConfigIds = $this->systemConfigRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        if (empty($systemConfigIds)) {
            return;
        }

        $ids = array_map(static function ($id) {
            return ['id' => $id];
        }, $systemConfigIds);

        $this->systemConfigRepository->delete($ids, Context::createDefaultContext());
    }

    private function load(?string $salesChannelId): array
    {
        $key = $salesChannelId ?? 'global';

        if (isset($this->configs[$key])) {
            return $this->configs[$key];
        }

        $criteria = new Criteria();
        $criteria->setTitle('system-config::load');

        if ($salesChannelId === null) {
            $criteria->addFilter(new EqualsFilter('salesChannelId', null));
        } else {
            $criteria->addFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new EqualsFilter('salesChannelId', $salesChannelId),
                        new EqualsFilter('salesChannelId', null),
                    ]
                )
            );
        }

        $criteria->addSorting(
            new FieldSorting('salesChannelId', FieldSorting::ASCENDING),
            new FieldSorting('id', FieldSorting::ASCENDING)
        );
        $criteria->setLimit(500);

        $systemConfigs = new SystemConfigCollection();
        $iterator = new RepositoryIterator($this->systemConfigRepository, Context::createDefaultContext(), $criteria);

        while ($chunk = $iterator->fetch()) {
            $systemConfigs->merge($chunk->getEntities());
        }

        $this->configs[$key] = $this->buildSystemConfigArray($systemConfigs);

        return $this->configs[$key];
    }

    /**
     * The keys of the system configs look like `core.loginRegistration.showPhoneNumberField`.
     * This method splits those strings and builds an array structure
     *
     * ```
     * Array
     * (
     *     [core] => Array
     *         (
     *             [loginRegistration] => Array
     *                 (
     *                     [showPhoneNumberField] => 'someValue'
     *                 )
     *         )
     * )
     * ```
     */
    private function buildSystemConfigArray(SystemConfigCollection $systemConfigs): array
    {
        $configValues = [];

        foreach ($systemConfigs as $systemConfig) {
            $keys = explode('.', $systemConfig->getConfigurationKey());

            $configValues = $this->getSubArray($configValues, $keys, $systemConfig->getConfigurationValue());
        }

        return $this->filterNotActivatedPlugins($configValues);
    }

    private function filterNotActivatedPlugins(array $configValues): array
    {
        $notActivatedPlugins = $this->getNotActivatedPlugins();
        foreach (array_keys($configValues) as $key) {
            $notActivatedPlugin = $notActivatedPlugins->filter(function (PluginEntity $plugin) use ($key) {
                return $plugin->getName() === $key;
            })->first();

            if ($notActivatedPlugin) {
                unset($configValues[$key]);
            }
        }

        return $configValues;
    }

    private function getSubArray(array $configValues, array $keys, $value): array
    {
        $key = array_shift($keys);

        if (empty($keys)) {
            $configValues[$key] = $value;
        } else {
            if (!\array_key_exists($key, $configValues)) {
                $configValues[$key] = [];
            }

            $configValues[$key] = $this->getSubArray($configValues[$key], $keys, $value);
        }

        return $configValues;
    }

    /**
     * @throws InvalidKeyException
     * @throws InvalidUuidException
     */
    private function validate(string $key, ?string $salesChannelId): void
    {
        $key = trim($key);
        if ($key === '') {
            throw new InvalidKeyException('key may not be empty');
        }
        if ($salesChannelId && !Uuid::isValid($salesChannelId)) {
            throw new InvalidUuidException($salesChannelId);
        }
    }

    private function getId(string $key, ?string $salesChannelId = null): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('configurationKey', $key),
            new EqualsFilter('salesChannelId', $salesChannelId)
        );

        $ids = $this->systemConfigRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return array_shift($ids);
    }

    private function getNotActivatedPlugins(): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', false));

        return $this->pluginRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }
}
