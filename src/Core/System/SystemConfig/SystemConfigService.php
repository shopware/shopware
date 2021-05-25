<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\InvalidDomainException;
use Shopware\Core\System\SystemConfig\Exception\InvalidKeyException;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
     * @var array
     */
    private $keys = ['all' => true];

    /**
     * @var array
     */
    private $traces = [];

    /**
     * @var AbstractSystemConfigLoader
     */
    private $loader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $systemConfigRepository,
        ConfigReader $configReader,
        AbstractSystemConfigLoader $loader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->connection = $connection;
        $this->systemConfigRepository = $systemConfigRepository;
        $this->configReader = $configReader;
        $this->loader = $loader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function buildName(string $key): string
    {
        return 'config.' . $key;
    }

    /**
     * @return array|bool|float|int|string|null
     */
    public function get(string $key, ?string $salesChannelId = null)
    {
        foreach (array_keys($this->keys) as $trace) {
            $this->traces[$trace][self::buildName($key)] = true;
        }

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
     * @internal should not be used in storefront or store api. The cache layer caches all accessed config keys and use them as cache tag.
     *
     * gets all available shop configs and returns them as an array
     */
    public function all(?string $salesChannelId = null): array
    {
        return $this->load($salesChannelId);
    }

    /**
     * @internal should not be used in storefront or store api. The cache layer caches all accessed config keys and use them as cache tag.
     *
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

            $this->eventDispatcher->dispatch(new SystemConfigChangedEvent($key, $value, $salesChannelId));

            return;
        }

        $data = [
            'id' => $id ?? Uuid::randomHex(),
            'configurationKey' => $key,
            'configurationValue' => $value,
            'salesChannelId' => $salesChannelId,
        ];
        $this->systemConfigRepository->upsert([$data], Context::createDefaultContext());
        $this->eventDispatcher->dispatch(new SystemConfigChangedEvent($key, $value, $salesChannelId));
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

    /**
     * @return mixed|null All kind of data could be cached
     */
    public function trace(string $key, \Closure $param)
    {
        $this->traces[$key] = [];
        $this->keys[$key] = true;

        $result = $param();

        unset($this->keys[$key]);

        return $result;
    }

    public function getTrace(string $key): array
    {
        $trace = isset($this->traces[$key]) ? array_keys($this->traces[$key]) : [];
        unset($this->traces[$key]);

        return $trace;
    }

    private function load(?string $salesChannelId): array
    {
        $key = $salesChannelId ?? 'global';

        if (isset($this->configs[$key])) {
            return $this->configs[$key];
        }

        $this->configs[$key] = $this->loader->load($salesChannelId);

        return $this->configs[$key];
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
}
