<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class CustomerNumberRangeConfigService implements ResetInterface
{
    private const GLOBAL_CACHE_KEY = 'global';

    /**
     * @var array<string, array{id: string, pattern: string}|null>
     */
    private array $cache = [];

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function getPatternConfigId(?string $salesChannelId): ?string
    {
        return $this->getPatternConfig($salesChannelId)['id'] ?? null;
    }

    public function reset(): void
    {
        $this->cache = [];
    }

    /**
     * @return array{id: string, pattern:string}|null
     */
    public function getPatternConfig(?string $salesChannelId): ?array
    {
        $cacheKey = $salesChannelId ?? self::GLOBAL_CACHE_KEY;
        if (\array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('number_range.id', 'number_range.pattern')
            ->from('number_range')
            ->innerJoin(
                'number_range',
                'number_range_type',
                'number_range_type',
                'number_range_type.id = number_range.type_id',
            )
            ->where('number_range_type.technical_name = :typeName')
            ->setParameter('typeName', CustomerDefinition::ENTITY_NAME)
            ->orderBy('number_range.global', 'ASC')
            ->addOrderBy('number_range_type.global', 'ASC');

        if ($salesChannelId === null) {
            $queryBuilder->andWhere('(number_range_type.global = 1 OR number_range.global = 1)');
        } else {
            $queryBuilder
                ->leftJoin(
                    'number_range',
                    'number_range_sales_channel',
                    'number_range_sales_channel',
                    'number_range.id = number_range_sales_channel.number_range_id',
                )
                ->andWhere('(number_range_sales_channel.sales_channel_id = :salesChannelId OR number_range_type.global = 1 OR number_range.global = 1)')
                ->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId));
        }

        /** @var array{id: string, pattern: string}|false $configuration */
        $configuration = $queryBuilder->executeQuery()->fetchAssociative();

        if ($configuration === false || !isset($configuration['id'], $configuration['pattern'])) {
            return $this->cache[$cacheKey] = null;
        }

        $configuration['id'] = Uuid::fromBytesToHex($configuration['id']);

        return $this->cache[$cacheKey] = $configuration;
    }
}
