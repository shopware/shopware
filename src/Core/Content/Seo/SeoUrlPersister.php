<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\Event\SeoUrlUpdateEvent;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class SeoUrlPersister
{
    /**
     * @internal
     *
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $seoUrlRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @param array<string> $foreignKeys
     * @param iterable<array<string, mixed>|SeoUrlEntity> $seoUrls
     */
    public function updateSeoUrls(Context $context, string $routeName, array $foreignKeys, iterable $seoUrls, SalesChannelEntity $salesChannel): void
    {
        $languageId = $context->getLanguageId();
        $canonicals = $this->findCanonicalPaths($routeName, $languageId, $foreignKeys);
        $dateTime = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $updatedFks = [];
        $obsoleted = [];

        $processed = [];

        $seoPathInfos = [];

        $salesChannelId = $salesChannel->getId();
        $updates = [];
        foreach ($seoUrls as $seoUrl) {
            if ($seoUrl instanceof SeoUrlEntity) {
                $seoUrl = $seoUrl->jsonSerialize();
            }
            $updates[] = $seoUrl;

            $fk = $seoUrl['foreignKey'];
            $salesChannelId = $seoUrl['salesChannelId'] ??= null;

            // skip duplicates
            if (isset($processed[$fk][$salesChannelId])) {
                continue;
            }

            if (!isset($processed[$fk])) {
                $processed[$fk] = [];
            }
            $processed[$fk][$salesChannelId] = true;

            $updatedFks[] = $fk;

            if (!empty($seoUrl['error'])) {
                continue;
            }
            $existing = $canonicals[$fk][$salesChannelId] ?? null;

            if ($existing) {
                // entity has override or does not change
                /** @phpstan-ignore-next-line PHPStan could not recognize the array generated from the jsonSerialize method of the SeoUrlEntity */
                if ($this->skipUpdate($existing, $seoUrl)) {
                    continue;
                }
                $obsoleted[] = $existing['id'];
            }

            $seoPathInfos[] = $seoUrl['seoPathInfo'];

            $insert = [];
            $insert['id'] = Uuid::randomBytes();

            if ($salesChannelId) {
                $insert['sales_channel_id'] = Uuid::fromHexToBytes($salesChannelId);
            }
            $insert['language_id'] = Uuid::fromHexToBytes($languageId);
            $insert['foreign_key'] = Uuid::fromHexToBytes($fk);

            $insert['path_info'] = $seoUrl['pathInfo'];
            $insert['seo_path_info'] = ltrim((string) $seoUrl['seoPathInfo'], '/');

            $insert['route_name'] = $routeName;
            $insert['is_canonical'] = ($seoUrl['isCanonical'] ?? true) ? 1 : null;
            $insert['is_modified'] = ($seoUrl['isModified'] ?? false) ? 1 : 0;
            $insert['is_deleted'] = ($seoUrl['isDeleted'] ?? true) ? 1 : 0;

            $insert['created_at'] = $dateTime;

            $insertQuery->addInsert($this->seoUrlRepository->getDefinition()->getEntityName(), $insert);
        }

        $inuseSeoUrls = $this->findInUseCanonicalSeoUrls($seoPathInfos, $salesChannelId);

        RetryableTransaction::retryable($this->connection, function () use ($obsoleted, $insertQuery, $foreignKeys, $updatedFks, $salesChannelId): void {
            $this->obsoleteIds($obsoleted, $salesChannelId);
            $insertQuery->execute();

            $deletedIds = array_diff($foreignKeys, $updatedFks);
            $notDeletedIds = array_unique(array_intersect($foreignKeys, $updatedFks));

            $this->markAsDeleted(true, $deletedIds, $salesChannelId);
            $this->markAsDeleted(false, $notDeletedIds, $salesChannelId);
        });

        // When a seoPathInfo is added that is already associated with a foreignKey, EX: Entity A,
        // the existing row is seamlessly replaced due to the useReplace flag being set to true within the MultiInsertQueryQueue configuration above.
        // Hence, we have to find the default seoUrls for Entity A and update it accordingly to set is_canonical and is_modified to true,
        // thereby preserving the canonical SEO URL for Entity A.
        $this->updateCanonicalSeoUrls($inuseSeoUrls);

        $this->eventDispatcher->dispatch(new SeoUrlUpdateEvent($updates));
    }

    /**
     * @param array{isModified: bool, seoPathInfo: string, salesChannelId: string} $existing
     * @param array{isModified?: bool, seoPathInfo: string, salesChannelId: string} $seoUrl
     */
    private function skipUpdate(array $existing, array $seoUrl): bool
    {
        if ($existing['isModified'] && !($seoUrl['isModified'] ?? false) && trim($seoUrl['seoPathInfo']) !== '') {
            return true;
        }

        return $seoUrl['seoPathInfo'] === $existing['seoPathInfo']
            && $seoUrl['salesChannelId'] === $existing['salesChannelId'];
    }

    /**
     * @param array<string> $foreignKeys
     *
     * @return array<string, mixed>
     */
    private function findCanonicalPaths(string $routeName, string $languageId, array $foreignKeys): array
    {
        $fks = Uuid::fromHexToBytesList($foreignKeys);
        $languageId = Uuid::fromHexToBytes($languageId);

        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(seo_url.id)) as id',
            'LOWER(HEX(seo_url.foreign_key)) foreignKey',
            'LOWER(HEX(seo_url.sales_channel_id)) salesChannelId',
            'seo_url.is_modified as isModified',
            'seo_url.seo_path_info seoPathInfo',
        );
        $query->from('seo_url', 'seo_url');

        $query->andWhere('seo_url.route_name = :routeName');
        $query->andWhere('seo_url.language_id = :language_id');
        $query->andWhere('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.foreign_key IN (:foreign_keys)');

        $query->setParameter('routeName', $routeName);
        $query->setParameter('language_id', $languageId);
        $query->setParameter('foreign_keys', $fks, ArrayParameterType::BINARY);

        $rows = $query->executeQuery()->fetchAllAssociative();

        $canonicals = [];
        foreach ($rows as $row) {
            $row['isModified'] = (bool) $row['isModified'];
            $foreignKey = (string) $row['foreignKey'];
            if (!isset($canonicals[$foreignKey])) {
                $canonicals[$foreignKey] = [$row['salesChannelId'] => $row];

                continue;
            }
            $canonicals[$foreignKey][$row['salesChannelId']] = $row;
        }

        return $canonicals;
    }

    /**
     * @param array<string> $seoPathInfos
     *
     * @return array<array<string, mixed>>
     */
    private function findInUseCanonicalSeoUrls(array $seoPathInfos, ?string $salesChannelId = null): array
    {
        if (empty($seoPathInfos)) {
            return [];
        }

        $query = 'SELECT id, language_id languageId, sales_channel_id salesChannelId, foreign_key foreignKey, route_name routeName
        FROM seo_url
        WHERE is_canonical = 1 AND seo_path_info IN (:seoPathInfos)';

        $params = ['seoPathInfos' => $seoPathInfos];
        $types = ['seoPathInfos' => ArrayParameterType::BINARY];

        if ($salesChannelId !== null) {
            $query .= ' AND sales_channel_id = :salesChannelId';
            $params['salesChannelId'] = $salesChannelId;
        }

        return $this->connection->fetchAllAssociative($query, $params, $types);
    }

    /**
     * Find the earliest valid SEO URL created. This means it is the default SEO URL and update the `is_canonical` and `is_modified` fields.
     *
     * @param array<array<string, mixed>> $seoUrls
     */
    private function updateCanonicalSeoUrls(array $seoUrls): void
    {
        if (empty($seoUrls)) {
            return;
        }

        $ids = [];
        foreach ($seoUrls as $seoUrl) {
            $id = $this->connection->fetchOne(
                'SELECT HEX(id) AS id
                 FROM seo_url
                 WHERE language_id = :languageId
                   AND foreign_key = :foreignKey
                   AND sales_channel_id = :salesChannelId
                   AND route_name = :routeName
                   AND is_canonical IS NULL AND is_deleted = 0
                 ORDER BY created_at ASC
                 LIMIT 1',
                [
                    'languageId' => $seoUrl['languageId'],
                    'foreignKey' => $seoUrl['foreignKey'],
                    'salesChannelId' => $seoUrl['salesChannelId'],
                    'routeName' => (string) $seoUrl['routeName'],
                ]
            );

            if ($id !== false) {
                $ids[] = $id;
            }
        }

        if (empty($ids)) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE seo_url SET is_canonical = 1, is_modified = 1 WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $ids
     */
    private function obsoleteIds(array $ids, ?string $salesChannelId): void
    {
        if (empty($ids)) {
            return;
        }

        $ids = Uuid::fromHexToBytesList($ids);

        $query = $this->connection->createQueryBuilder()
            ->update('seo_url')
            ->set('is_canonical', 'NULL')
            ->where('id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::BINARY);

        if ($salesChannelId) {
            $query->andWhere('sales_channel_id = :salesChannelId');
            $query->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId));
        }

        RetryableQuery::retryable($this->connection, function () use ($query): void {
            $query->executeStatement();
        });
    }

    /**
     * @param array<string> $ids
     */
    private function markAsDeleted(bool $deleted, array $ids, ?string $salesChannelId): void
    {
        if (empty($ids)) {
            return;
        }

        $ids = Uuid::fromHexToBytesList($ids);
        $query = $this->connection->createQueryBuilder()
            ->update('seo_url')
            ->set('is_deleted', $deleted ? '1' : '0')
            ->where('foreign_key IN (:fks)')
            ->setParameter('fks', $ids, ArrayParameterType::BINARY);

        if ($salesChannelId) {
            $query->andWhere('sales_channel_id = :salesChannelId');
            $query->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId));
        }

        $query->executeStatement();
    }
}
