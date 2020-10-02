<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

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
     * @var CacheClearer
     */
    private $cache;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $seoUrlRepository,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        CacheClearer $cache
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
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $updatedFks = [];
        $obsoleted = [];

        $seoUrlIds = [];

        $processed = [];

        // should be provided
        $salesChannelId = null;
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
            if (isset($seoUrl['error'])) {
                continue;
            }
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
            }
            $insert['language_id'] = Uuid::fromHexToBytes($languageId);
            $insert['foreign_key'] = Uuid::fromHexToBytes($fk);

            $insert['path_info'] = $seoUrl['pathInfo'];
            $insert['seo_path_info'] = ltrim($seoUrl['seoPathInfo'], '/');

            $insert['route_name'] = $routeName;
            $insert['is_canonical'] = ($seoUrl['isCanonical'] ?? true) ? 1 : null;
            $insert['is_modified'] = ($seoUrl['isModified'] ?? false) ? 1 : 0;
            $insert['is_deleted'] = ($seoUrl['isDeleted'] ?? true) ? 1 : 0;

            $insert['created_at'] = $dateTime;

            $insertQuery->addInsert($this->seoUrlRepository->getDefinition()->getEntityName(), $insert);
        }

        $this->connection->beginTransaction();

        try {
            $this->obsoleteIds($obsoleted, $dateTime);

            RetryableQuery::retryable(function () use ($insertQuery): void {
                $insertQuery->execute();
            });

            $deletedIds = array_diff($foreignKeys, $updatedFks);
            $notDeletedIds = array_unique(array_intersect($foreignKeys, $updatedFks));

            $this->markAsDeleted(true, $deletedIds, $dateTime, $salesChannelId);
            $this->markAsDeleted(false, $notDeletedIds, $dateTime, $salesChannelId);

            if (!$this->connection->isRollbackOnly()) {
                $this->connection->commit();
            } else {
                $this->connection->rollBack();
            }
        } catch (\Throwable $e) {
            $this->connection->rollBack();
        }

        $this->invalidateEntityCache($seoUrlIds);
    }

    private function skipUpdate($existing, $seoUrl): bool
    {
        if ($existing['isModified'] && !($seoUrl['isModified'] ?? false) && trim($seoUrl['seoPathInfo']) !== '') {
            return true;
        }

        return $seoUrl['seoPathInfo'] === $existing['seoPathInfo']
            && $seoUrl['salesChannelId'] === $existing['salesChannelId'];
    }

    private function findCanonicalPaths(string $routeName, string $languageId, array $foreignKeys): array
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
            ->set('is_canonical', 'NULL')
            ->set('updated_at', ':dateTime')
            ->where('id IN (:ids)')
            ->setParameter('dateTime', $dateTime)
            ->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY)
            ->execute();
    }

    private function markAsDeleted(bool $deleted, array $ids, string $dateTime, ?string $salesChannelId): void
    {
        if (empty($ids)) {
            return;
        }

        $ids = Uuid::fromHexToBytesList($ids);
        $query = $this->connection->createQueryBuilder()
            ->update('seo_url')
            ->set('is_deleted', $deleted ? '1' : '0')
            ->set('updated_at', ':dateTime')
            ->where('foreign_key IN (:fks)')
            ->setParameter('dateTime', $dateTime)
            ->setParameter('fks', $ids, Connection::PARAM_STR_ARRAY);

        if ($salesChannelId) {
            $query->andWhere('sales_channel_id = :salesChannelId');
            $query->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId));
        }

        RetryableQuery::retryable(function () use ($query): void {
            $query->execute();
        });
    }

    private function invalidateEntityCache(array $seoUrlIds = []): void
    {
        $tags = $this->cacheKeyGenerator->getSearchTags($this->seoUrlRepository->getDefinition(), new Criteria());

        if (!empty($seoUrlIds)) {
            foreach ($seoUrlIds as $id) {
                $tags[] = $this->cacheKeyGenerator->getEntityTag($id, SeoUrlDefinition::ENTITY_NAME);
            }
        }

        $this->cache->invalidateTags($tags);
    }
}
