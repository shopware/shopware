<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Exception\InvalidDomainException;
use Shopware\Core\System\SystemConfig\Exception\InvalidKeyException;

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

    public function __construct(Connection $connection, EntityRepositoryInterface $systemConfigRepository)
    {
        $this->connection = $connection;
        $this->systemConfigRepository = $systemConfigRepository;
    }

    public function get(string $key, ?string $salesChannelId = null)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new EqualsFilter('salesChannelId', null),
                new EqualsFilter('salesChannelId', $salesChannelId),
            ]
        ));
        $criteria->addFilter(new EqualsFilter('configurationKey', $key));
        $criteria->addSorting(new FieldSorting('salesChannelId', FieldSorting::ASCENDING));

        // this should return at most two entities
        /** @var EntitySearchResult $result */
        $result = $this->systemConfigRepository->search($criteria, Context::createDefaultContext());
        $default = $result->first();
        $override = $result->last();

        if ($override) {
            return $override->getConfigurationValue();
        }

        return $default ? $default->getConfigurationValue() : null;
    }

    public function getDomain(string $domain, ?string $salesChannelId = null): array
    {
        $domain = trim($domain);
        if ($domain === '') {
            throw new InvalidDomainException('Empty domain');
        }
        $domain = rtrim($domain, '.') . '.';

        $escapedDomain = str_replace('%', '\\%', $domain);
        $ids = $this->connection->executeQuery('
            SELECT LOWER(HEX(id))
            FROM system_config
            WHERE (sales_channel_id IS NULL OR sales_channel_id = :sales_channel_id)
            AND configuration_key LIKE :prefix
            ORDER BY configuration_key, sales_channel_id ASC',
            [
                'sales_channel_id' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
                'prefix' => $escapedDomain . '%',
            ])->fetchAll(FetchMode::COLUMN);

        if (empty($ids)) {
            return [];
        }

        $criteria = new Criteria($ids);
        $collection = $this->systemConfigRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        $collection->sortByIdArray($ids);
        $merged = [];
        foreach ($collection as $cur) {
            // use the last one with the same key. entities with sales_channel_id === null are sorted before the others
            $merged[$cur->getConfigurationKey()] = $cur->getConfigurationValue();
        }

        return $merged;
    }

    public function set(string $key, $value, ?string $salesChannelId = null): void
    {
        $key = trim($key);
        if ($key === '') {
            throw new InvalidKeyException('key may not be empty');
        }
        if ($salesChannelId && !Uuid::isValid($salesChannelId)) {
            throw new InvalidUuidException($salesChannelId);
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('configurationKey', $key),
            new EqualsFilter('salesChannelId', $salesChannelId)
        );

        $ids = $this->systemConfigRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        $data = [
            'id' => $ids[0] ?? Uuid::randomHex(),
            'configurationKey' => $key,
            'configurationValue' => $value,
            'salesChannelId' => $salesChannelId,
        ];
        $this->systemConfigRepository->upsert([$data], Context::createDefaultContext());
    }
}
