<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use function array_shift;
use function explode;
use function json_decode;

#[Package('system-settings')]
class SystemConfigLoader extends AbstractSystemConfigLoader
{
    /**
     * @internal
     */
    public function __construct(
        protected Connection $connection,
        protected Kernel $kernel
    ) {
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(?string $salesChannelId): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('system_config');
        $query->select(['configuration_key', 'configuration_value']);

        if ($salesChannelId === null) {
            $query
                ->andWhere('sales_channel_id IS NULL');
        } else {
            $query->andWhere('sales_channel_id = :salesChannelId OR system_config.sales_channel_id IS NULL');
            $query->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId));
        }

        $query->addOrderBy('sales_channel_id', 'ASC');

        $result = $query->executeQuery();

        return $this->buildSystemConfigArray($result->fetchAllKeyValue());
    }

    private function buildSystemConfigArray(array $systemConfigs): array
    {
        $configValues = [];

        foreach ($systemConfigs as $key => $value) {
            $keys = explode('.', (string) $key);

            if ($value !== null) {
                $value = json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);

                if ($value === false || !isset($value[ConfigJsonField::STORAGE_KEY])) {
                    $value = null;
                } else {
                    $value = $value[ConfigJsonField::STORAGE_KEY];
                }
            }

            $configValues = $this->getSubArray($configValues, $keys, $value);
        }

        return $this->filterNotActivatedPlugins($configValues);
    }

    /**
     * @param array|bool|float|int|string|null $value
     */
    private function getSubArray(array $configValues, array $keys, $value): array
    {
        $key = array_shift($keys);

        if (empty($keys)) {
            // Configs can be overwritten with sales_channel_id
            $inheritedValuePresent = \array_key_exists($key, $configValues);
            $valueConsideredEmpty = !\is_bool($value) && empty($value);

            if ($inheritedValuePresent && $valueConsideredEmpty) {
                return $configValues;
            }

            $configValues[$key] = $value;
        } else {
            if (!\array_key_exists($key, $configValues)) {
                $configValues[$key] = [];
            }

            $configValues[$key] = $this->getSubArray($configValues[$key], $keys, $value);
        }

        return $configValues;
    }

    private function filterNotActivatedPlugins(array $configValues): array
    {
        $notActivatedPlugins = $this->kernel->getPluginLoader()->getPluginInstances()->filter(fn (Plugin $plugin) => !$plugin->isActive())->all();

        foreach ($notActivatedPlugins as $plugin) {
            if (isset($configValues[$plugin->getName()])) {
                unset($configValues[$plugin->getName()]);
            }
        }

        return $configValues;
    }
}
