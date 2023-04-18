<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Util\XmlReader;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook;
use Shopware\Core\System\SystemConfig\Event\SystemConfigDomainLoadedEvent;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\InvalidDomainException;
use Shopware\Core\System\SystemConfig\Exception\InvalidKeyException;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use function json_decode;

#[Package('system-settings')]
class SystemConfigService implements ResetInterface
{
    /**
     * @var array<string, bool>
     */
    private array $keys = ['all' => true];

    /**
     * @var array<mixed>
     */
    private array $traces = [];

    /**
     * @var array<string, string>|null
     */
    private ?array $appMapping = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ConfigReader $configReader,
        private readonly AbstractSystemConfigLoader $loader,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public static function buildName(string $key): string
    {
        return 'config.' . $key;
    }

    /**
     * @return array<mixed>|bool|float|int|string|null
     */
    public function get(string $key, ?string $salesChannelId = null)
    {
        foreach (array_keys($this->keys) as $trace) {
            $this->traces[$trace][self::buildName($key)] = true;
        }

        $config = $this->loader->load($salesChannelId);

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
     *
     * @return array<mixed>
     */
    public function all(?string $salesChannelId = null): array
    {
        return $this->loader->load($salesChannelId);
    }

    /**
     * @internal should not be used in storefront or store api. The cache layer caches all accessed config keys and use them as cache tag.
     *
     * @throws InvalidDomainException
     *
     * @return array<mixed>
     */
    public function getDomain(string $domain, ?string $salesChannelId = null, bool $inherit = false): array
    {
        $domain = trim($domain);
        if ($domain === '') {
            throw new InvalidDomainException('Empty domain');
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select(['configuration_key', 'configuration_value'])
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
            ->addOrderBy('sales_channel_id', 'ASC')
            ->setParameter('prefix', $escapedDomain . '%')
            ->setParameter('salesChannelId', $salesChannelId);

        $configs = $queryBuilder->executeQuery()->fetchAllNumeric();

        if ($configs === []) {
            return [];
        }

        $merged = [];

        foreach ($configs as [$key, $value]) {
            if ($value !== null) {
                $value = json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);

                if ($value === false || !isset($value[ConfigJsonField::STORAGE_KEY])) {
                    $value = null;
                } else {
                    $value = $value[ConfigJsonField::STORAGE_KEY];
                }
            }

            $inheritedValuePresent = \array_key_exists($key, $merged);
            $valueConsideredEmpty = !\is_bool($value) && empty($value);

            if ($inheritedValuePresent && $valueConsideredEmpty) {
                continue;
            }

            $merged[$key] = $value;
        }

        $event = new SystemConfigDomainLoadedEvent($domain, $merged, $inherit, $salesChannelId);
        $this->eventDispatcher->dispatch($event);

        return $event->getConfig();
    }

    /**
     * @param array<mixed>|bool|float|int|string|null $value
     */
    public function set(string $key, $value, ?string $salesChannelId = null): void
    {
        $this->setMultiple([$key => $value], $salesChannelId);
    }

    /**
     * @param array<string, array<mixed>|bool|float|int|string|null> $values
     */
    public function setMultiple(array $values, ?string $salesChannelId = null): void
    {
        $where = $salesChannelId ? 'sales_channel_id = :salesChannelId' : 'sales_channel_id IS NULL';

        $existingIds = $this->connection
            ->fetchAllKeyValue(
                'SELECT configuration_key, id FROM system_config WHERE ' . $where . ' and configuration_key IN (:configurationKeys)',
                [
                    'salesChannelId' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
                    'configurationKeys' => array_keys($values),
                ],
                [
                    'configurationKeys' => ArrayParameterType::STRING,
                ]
            );

        $toBeDeleted = [];
        $insertQueue = new MultiInsertQueryQueue($this->connection, 100, false, true);
        $events = [];

        foreach ($values as $key => $value) {
            $key = trim($key);
            $this->validate($key, $salesChannelId);

            $event = new BeforeSystemConfigChangedEvent($key, $value, $salesChannelId);
            $this->eventDispatcher->dispatch($event);

            // On null value, delete the config
            if ($value === null) {
                $toBeDeleted[] = $key;

                $events[] = new SystemConfigChangedEvent($key, $value, $salesChannelId);

                continue;
            }

            if (isset($existingIds[$key])) {
                $this->connection->update(
                    'system_config',
                    [
                        'configuration_value' => Json::encode(['_value' => $value]),
                        'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ],
                    [
                        'id' => $existingIds[$key],
                    ]
                );

                $events[] = new SystemConfigChangedEvent($key, $value, $salesChannelId);

                continue;
            }

            $insertQueue->addInsert(
                'system_config',
                [
                    'id' => Uuid::randomBytes(),
                    'configuration_key' => $key,
                    'configuration_value' => Json::encode(['_value' => $value]),
                    'sales_channel_id' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            );

            $events[] = new SystemConfigChangedEvent($key, $value, $salesChannelId);
        }

        // Delete all null values
        if (!empty($toBeDeleted)) {
            $qb = $this->connection
                ->createQueryBuilder()
                ->where('configuration_key IN (:keys)')
                ->setParameter('keys', $toBeDeleted, ArrayParameterType::STRING);

            if ($salesChannelId) {
                $qb->andWhere('sales_channel_id = :salesChannelId')
                    ->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId));
            } else {
                $qb->andWhere('sales_channel_id IS NULL');
            }

            $qb->delete('system_config')
                ->executeStatement();
        }

        $insertQueue->execute();

        // Dispatch events that the given values have been changed
        foreach ($events as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        $this->eventDispatcher->dispatch(new SystemConfigChangedHook($values, $this->getAppMapping()));
    }

    public function delete(string $key, ?string $salesChannel = null): void
    {
        $this->setMultiple([$key => null], $salesChannel);
    }

    /**
     * Fetches default values from bundle configuration and saves it to database
     */
    public function savePluginConfiguration(Bundle $bundle, bool $override = false): void
    {
        try {
            $config = $this->configReader->getConfigFromBundle($bundle);
        } catch (BundleConfigNotFoundException) {
            return;
        }

        $prefix = $bundle->getName() . '.config.';

        $this->saveConfig($config, $prefix, $override);
    }

    /**
     * @param array<mixed> $config
     */
    public function saveConfig(array $config, string $prefix, bool $override): void
    {
        $relevantSettings = $this->getDomain($prefix);

        foreach ($config as $card) {
            foreach ($card['elements'] as $element) {
                $key = $prefix . $element['name'];
                if (!isset($element['defaultValue'])) {
                    continue;
                }

                $value = XmlReader::phpize($element['defaultValue']);
                if ($override || !isset($relevantSettings[$key])) {
                    $this->set($key, $value);
                }
            }
        }
    }

    public function deletePluginConfiguration(Bundle $bundle): void
    {
        try {
            $config = $this->configReader->getConfigFromBundle($bundle);
        } catch (BundleConfigNotFoundException) {
            return;
        }

        $this->deleteExtensionConfiguration($bundle->getName(), $config);
    }

    /**
     * @param array<mixed> $config
     */
    public function deleteExtensionConfiguration(string $extensionName, array $config): void
    {
        $prefix = $extensionName . '.config.';

        $configKeys = [];
        foreach ($config as $card) {
            foreach ($card['elements'] as $element) {
                $configKeys[] = $prefix . $element['name'];
            }
        }

        if (empty($configKeys)) {
            return;
        }

        $this->setMultiple(array_fill_keys($configKeys, null));
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

    /**
     * @return array<mixed>
     */
    public function getTrace(string $key): array
    {
        $trace = isset($this->traces[$key]) ? array_keys($this->traces[$key]) : [];
        unset($this->traces[$key]);

        return $trace;
    }

    public function reset(): void
    {
        $this->appMapping = null;
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

    /**
     * @return array<string, string>
     */
    private function getAppMapping(): array
    {
        if ($this->appMapping !== null) {
            return $this->appMapping;
        }

        /** @var array<string, string> $allKeyValue */
        $allKeyValue = $this->connection->fetchAllKeyValue('SELECT LOWER(HEX(id)), name FROM app');

        return $this->appMapping = $allKeyValue;
    }
}
