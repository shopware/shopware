<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class SeoUrlPersister
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlRepository;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $seoUrlRepository,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        TagAwareAdapterInterface $cache
    ) {
        $this->connection = $connection;
        $this->seoUrlRepository = $seoUrlRepository;

        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
    }

    public function updateSeoUrls(Context $context, string $routeName, array $foreignKeys, iterable $seoUrls): void
    {
        $languageId = $context->getLanguageId();
        $canonicals = $this->findCanonicalPaths($routeName, $languageId, $foreignKeys);
        $dateTime = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, false, false);

        $updatedFks = [];
        $obsoleted = [];

        $salesChannelIds = [];
        $seoUrlIds = [];

        $processed = [];
        $inserts = [];

        foreach ($seoUrls as $seoUrl) {
            if ($seoUrl instanceof \JsonSerializable) {
                $seoUrl = $seoUrl->jsonSerialize();
            }
            $fk = $seoUrl['foreignKey'];
            /** @var string|null $salesChannelId */
            $salesChannelId = $seoUrl['salesChannelId'] = $seoUrl['salesChannelId'] ?? null;

            // skip duplicates
            if (isset($processed[$fk][$salesChannelId])) {
                continue;
            }

            if (!isset($processed[$fk])) {
                $processed[$fk] = [];
            }
            $processed[$fk][$salesChannelId] = true;

            $updatedFks[] = $fk;
            $existing = $canonicals[$fk][$salesChannelId] ?? null;

            if ($existing) {
                // entity has override or does not change
                if ($this->skipUpdate($existing, $seoUrl)) {
                    continue;
                }
                $obsoleted[] = $existing['id'];
                $seoUrlIds[] = $existing['id'];
            }

            $insert = [];
            $insert['id'] = Uuid::randomBytes();

            $seoUrlIds[] = Uuid::fromBytesToHex($insert['id']);

            if ($salesChannelId) {
                $insert['sales_channel_id'] = Uuid::fromHexToBytes($salesChannelId);
                $salesChannelIds[$salesChannelId] = $salesChannelId;
            }
            $insert['language_id'] = Uuid::fromHexToBytes($languageId);
            $insert['foreign_key'] = Uuid::fromHexToBytes($fk);

            $insert['path_info'] = $seoUrl['pathInfo'];
            $insert['seo_path_info'] = trim($seoUrl['seoPathInfo'], '/');

            $insert['route_name'] = $routeName;
            $insert['is_canonical'] = ($seoUrl['isCanonical'] ?? true) ? 1 : 0;
            $insert['is_modified'] = ($seoUrl['isModified'] ?? false) ? 1 : 0;

            $insert['is_valid'] = true;
            $insert['created_at'] = $dateTime;

            $insertQuery->addInsert($this->seoUrlRepository->getDefinition()->getEntityName(), $insert);
        }

        //$inserts = $this->reenableOldEntries($inserts, $languageId);
//        foreach ($inserts as $insert) {
//            $insertQuery->addInsert($this->seoUrlRepository->getDefinition()->getEntityName(), $insert);
//        }
        $insertQuery->execute();

        $this->invalidateEntityCache();

        $this->obsoleteIds($obsoleted, $dateTime);

        $deletedIds = array_diff($foreignKeys, $updatedFks);
        $this->markAsDeleted($deletedIds, $dateTime);

        $this->invalidateDuplicates($context->getLanguageId(), $salesChannelIds, $foreignKeys);

        $this->invalidateEntityCache($seoUrlIds);
    }

    private function reenableOldEntries(array $inserts, string $languageId): array
    {
        $languageId = Uuid::fromHexToBytes($languageId);
        $foreignKeys = array_column($inserts, 'foreign_key');
        $salesChannelIds = array_column($inserts, 'sales_channel_id');
        $seoUrls = array_column($inserts, 'seo_path_info');

        $query = $this->connection->createQueryBuilder();
        $query->select(['LOWER(HEX(seo_url.id)) as id',
            'LOWER(HEX(seo_url.foreign_key)) as fk',
            'LOWER(HEX(seo_url.sales_channel_id)) as sales_channel_id',
            'seo_url.seo_path_info as seo_path_info', ]);
        $query->from('seo_url', 'seo_url');

        $query->andWhere('seo_url.seo_path_info IN (:seo_urls)');
        $query->andWhere('seo_url.language_id = :language_ids');
        $query->andWhere('seo_url.foreign_key IN (:foreign_keys)');
        $query->andWhere('seo_url.sales_channel_id IN (:sales_channel_ids)');

        $query->setParameter('seo_urls', $seoUrls, Connection::PARAM_STR_ARRAY);
        $query->setParameter('language_ids', $languageId);
        $query->setParameter('foreign_keys', $foreignKeys, Connection::PARAM_STR_ARRAY);
        $query->setParameter('sales_channel_ids', $salesChannelIds, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);

        $newInserts = array_filter($inserts, function (array $insert) use ($rows) {
            $foreignKey = Uuid::fromBytesToHex($insert['foreign_key']);
            $url = $insert['seo_path_info'];

            $salesChannelId = null;
            if (isset($insert['sales_channel_id'])) {
                $salesChannelId = Uuid::fromBytesToHex($insert['sales_channel_id']);
            }

            $candidates = array_filter($rows, function ($row) use ($foreignKey, $salesChannelId, $url) {
                return $row['fk'] === $foreignKey
                        && $row['sales_channel_id'] === $salesChannelId
                        && $row['seo_path_info'] === $url;
            });

            return count($candidates) === 0;
        }
        );

        if (!empty($rows)) {
            $this->connection->executeQuery(
                'UPDATE seo_url SET is_valid = 1, is_canonical = 1 WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList(array_column($rows, 'id'))],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        }

        return $newInserts;
    }

    private function skipUpdate($existing, $seoUrl): bool
    {
        if ($existing['isModified'] && !($seoUrl['isModified'] ?? false) && trim($seoUrl['seoPathInfo']) !== '') {
            return true;
        }

        return $seoUrl['seoPathInfo'] === $existing['seoPathInfo']
            && $seoUrl['salesChannelId'] === $existing['salesChannelId'];
    }

    private function findCanonicalPaths($routeName, string $languageId, array $foreignKeys): array
    {
        $fks = Uuid::fromHexToBytesList($foreignKeys);
        $languageId = Uuid::fromHexToBytes($languageId);

        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(seo_url.id)) as id',
            'LOWER(HEX(seo_url.foreign_key)) foreignKey',
            'LOWER(HEX(seo_url.sales_channel_id)) salesChannelId',
            'seo_url.is_modified as isModified',
            'seo_url.seo_path_info seoPathInfo',
        ]);
        $query->from('seo_url', 'seo_url');

        $query->andWhere('seo_url.route_name = :routeName');
        $query->andWhere('seo_url.language_id = :language_id');
        $query->andWhere('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.foreign_key IN (:foreign_keys)');

        $query->setParameter('routeName', $routeName);
        $query->setParameter('language_id', $languageId);
        $query->setParameter('foreign_keys', $fks, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll();

        $canonicals = [];
        foreach ($rows as $row) {
            $row['isModified'] = (bool) $row['isModified'];
            if (!isset($canonicals[$row['foreignKey']])) {
                $canonicals[$row['foreignKey']] = [$row['salesChannelId'] => $row];
                continue;
            }
            $canonicals[$row['foreignKey']][$row['salesChannelId']] = $row;
        }

        return $canonicals;
    }

    private function obsoleteIds(array $ids, string $dateTime): void
    {
        if (empty($ids)) {
            return;
        }
        $tags = $this->cacheKeyGenerator->getSearchTags($this->seoUrlRepository->getDefinition(), new Criteria());
        $this->cache->invalidateTags($tags);
        $ids = Uuid::fromHexToBytesList($ids);

        $this->connection->createQueryBuilder()
            ->update('seo_url')
            ->set('is_canonical', '0')
            ->set('updated_at', ':dateTime')
            ->where('id IN (:ids)')
            ->setParameter('dateTime', $dateTime)
            ->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY)
            ->execute();
    }

    private function markAsDeleted($ids, string $dateTime): void
    {
        if (empty($ids)) {
            return;
        }
        $ids = Uuid::fromHexToBytesList($ids);
        $this->connection->createQueryBuilder()
            ->update('seo_url')
            ->set('is_deleted', '1')
            ->set('updated_at', ':dateTime')
            ->where('foreign_key IN (:fks)')
            ->setParameter('dateTime', $dateTime)
            ->setParameter('fks', $ids, Connection::PARAM_STR_ARRAY)
            ->execute();
    }

    private function invalidateDuplicates(string $languageId, array $salesChannelIds, array $foreignKeys): void
    {
        $salesChannelIds = Uuid::fromHexToBytesList($salesChannelIds);
        $foreignKeys = Uuid::fromHexToBytesList($foreignKeys);
        $languageId = Uuid::fromHexToBytes($languageId);

        /*
         * If we find duplicates for a seo_path_info we need to mark all but one seo_url as invalid.
         * The newest seo_url wins. The ordering is established by the auto_increment column.
         */
        $dupSameFkIds = $this->connection->executeQuery(
            'SELECT DISTINCT invalid.id 
            FROM seo_url valid
            INNER JOIN seo_url invalid
                ON valid.seo_path_info = invalid.seo_path_info
                AND valid.language_id = invalid.language_id
                AND (valid.sales_channel_id = invalid.sales_channel_id
                    OR valid.sales_channel_id IS NULL AND invalid.sales_channel_id IS NULL
                ) AND valid.auto_increment > invalid.auto_increment # order
                AND valid.foreign_key = invalid.foreign_key
                AND invalid.foreign_key IN (:foreign_keys)
            WHERE (valid.sales_channel_id IN (:sales_channel_ids) OR valid.sales_channel_id IS NULL)
            AND valid.language_id = :language_id',
            ['language_id' => $languageId, 'sales_channel_ids' => $salesChannelIds, 'foreign_keys' => $foreignKeys],
            ['language_id' => ParameterType::STRING, 'sales_channel_ids' => Connection::PARAM_STR_ARRAY, 'foreign_keys' => Connection::PARAM_STR_ARRAY]
        )->fetchAll(FetchMode::COLUMN);

        if (!empty($dupSameFkIds)) {
            $this->connection->executeQuery(
                'UPDATE seo_url SET is_valid = 0 WHERE id IN (:ids)',
                ['ids' => $dupSameFkIds],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        }

        /*
         * If we find duplicates for a seo_path_info we need to mark all but one seo_url as invalid.
         * The newest seo_url wins. The ordering is established by the auto_increment column.
         */
        $dupIds = $this->connection->executeQuery(
            'SELECT DISTINCT invalid.id 
            FROM seo_url valid
            INNER JOIN seo_url invalid
                ON valid.seo_path_info = invalid.seo_path_info
                AND valid.language_id = invalid.language_id
                AND (valid.sales_channel_id = invalid.sales_channel_id
                    OR valid.sales_channel_id IS NULL AND invalid.sales_channel_id IS NULL
                ) AND valid.auto_increment < invalid.auto_increment # order
                AND invalid.foreign_key IN (:foreign_keys)
                AND invalid.foreign_key != valid.foreign_key
            WHERE (valid.sales_channel_id IN (:sales_channel_ids) OR valid.sales_channel_id IS NULL)
            AND valid.language_id = :language_id',
            ['language_id' => $languageId, 'sales_channel_ids' => $salesChannelIds, 'foreign_keys' => $foreignKeys],
            ['language_id' => ParameterType::STRING, 'sales_channel_ids' => Connection::PARAM_STR_ARRAY, 'foreign_keys' => Connection::PARAM_STR_ARRAY]
        )->fetchAll(FetchMode::COLUMN);

        if (empty($dupIds)) {
            return;
        }

        $this->connection->executeQuery(
            'UPDATE seo_url SET is_valid = 0 WHERE id IN (:ids)',
            ['ids' => $dupIds],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function invalidateEntityCache($seoUrlIds = []): void
    {
        $tags = $this->cacheKeyGenerator->getSearchTags($this->seoUrlRepository->getDefinition(), new Criteria());

        if (!empty($seoUrlIds)) {
            foreach ($seoUrlIds as $id) {
                $tags[] = $this->cacheKeyGenerator->getEntityTag($id, $this->seoUrlRepository->getDefinition());
            }
        }

        $this->cache->invalidateTags($tags);
    }
}
