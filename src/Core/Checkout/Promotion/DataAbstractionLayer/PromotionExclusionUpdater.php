<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

class PromotionExclusionUpdater
{
    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(EntityCacheKeyGenerator $cacheKeyGenerator, CacheClearer $cache, Connection $connection)
    {
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cacheClearer = $cache;
        $this->connection = $connection;
    }

    /**
     * function is called when a promotion is saved.
     * the exclusions of promotions will be checked and are written/deleted if necessary
     */
    public function update(array $ids): void
    {
        // if there are no ids, we don't have to do anything
        if (empty($ids)) {
            return;
        }

        $tags = [];

        foreach ($ids as $id) {
            // get exclusions for this id and prepare it as hex array
            $exclusions = $this->getExclusionIds($id);

            $this->addTags($tags, [$id]);

            // create empty array if there are no exclusions
            $promotionExclusions = [];

            if (count($exclusions) > 0) {
                $firstResult = array_shift($exclusions);
                if (array_key_exists('exclusion_ids', $firstResult)) {
                    // if there are exclusions, set them in array
                    $promotionExclusions = json_decode($firstResult['exclusion_ids']);
                }
            }

            // delete all references that are not in exclusions array of this entity
            $affectedRows = $this->deleteFromJSON($id, $promotionExclusions);

            $this->addTags($tags, $affectedRows);

            // if there are no references in exclusions we don't need to update anything
            if (count($promotionExclusions) === 0) {
                continue;
            }

            // check for corrupted data in database. If a excluded promotion could not be found it will not be present in results
            $results = $this->getExistingIds($promotionExclusions);

            $this->addTags($tags, $results);

            if (count($results) === count($promotionExclusions)) {
                // if there is no corrupted data we will add id to all excluded promotions too
                $this->addToJSON($id, $promotionExclusions);

                continue;
            }

            // there is corrupted data we have to update data with only valid exclusions
            $onlyAddThisExistingIds = [];

            // converting from hex to byte ensures that case sensitivity in hex values doesn't matter
            $resultValues = $this->convertHexArrayToByteArray($results);

            // select valid excluded promotions
            foreach ($promotionExclusions as $excludedId) {
                // if a value is not a valid hex value, we ignore this value
                if (!Uuid::isValid((string) $excludedId)) {
                    continue;
                }

                // if our byte value could be found in our byte array we add hex value to our array
                if (in_array(Uuid::fromHexToBytes($excludedId), $resultValues, true)) {
                    $onlyAddThisExistingIds[] = $excludedId;
                }
            }

            // write valid values to our promotion
            $this->updateJSON($id, $onlyAddThisExistingIds);

            // add exclusions to all excluded promotions too
            $this->addToJSON($id, $onlyAddThisExistingIds);
        }

        $this->cacheClearer->invalidateTags($tags);
    }

    /**
     * deletes all referenced exclusions in all promotions that id is not in excludeThisIds
     * returns affected hex uuids
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function deleteFromJSON(string $deleteId, array $excludeThisIds): array
    {
        $affectedIds = [];
        $tags = [];
        $sqlStatement = 'SELECT id from promotion WHERE JSON_CONTAINS(promotion.exclusion_ids, JSON_ARRAY(:value))';

        $params = ['value' => $deleteId];

        $types = [];

        if (count($excludeThisIds) > 0) {
            $sqlStatement .= ' AND id NOT IN (:excludedIds)';
            $params['excludedIds'] = $this->convertHexArrayToByteArray($excludeThisIds);
            $types['excludedIds'] = Connection::PARAM_STR_ARRAY;
        }

        $results = $this->connection->executeQuery($sqlStatement, $params, $types)->fetchAll();

        if (count($results) === 0) {
            return [];
        }

        foreach ($results as $row) {
            $affectedIds[] = $row['id'];

            $tags[] = Uuid::fromBytesToHex($row['id']);
        }

        RetryableQuery::retryable(function () use ($affectedIds, $deleteId): void {
            $sqlStatement = "
                UPDATE promotion
                SET promotion.exclusion_ids = JSON_REMOVE(promotion.exclusion_ids, JSON_UNQUOTE(JSON_SEARCH(promotion.exclusion_ids,'one', :value)))
                WHERE id IN(:affectedIds)
            ";

            $this->connection->executeUpdate($sqlStatement, ['value' => $deleteId, 'affectedIds' => $affectedIds], ['affectedIds' => Connection::PARAM_STR_ARRAY]);
        });

        return $tags;
    }

    /**
     * appends addId in all promotions that id is in ids
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function addToJSON(string $addId, array $ids): void
    {
        if (count($ids) < 1) {
            return;
        }

        RetryableQuery::retryable(function () use ($addId, $ids): void {
            $this->connection->executeUpdate(
                'UPDATE promotion
                 SET promotion.exclusion_ids = (JSON_ARRAY_APPEND(IFNULL(promotion.exclusion_ids,JSON_ARRAY()), \'$\', :value))
                 WHERE id IN (:addToTheseIds)
                 AND NOT JSON_CONTAINS(IFNULL(promotion.exclusion_ids, JSON_ARRAY()), JSON_ARRAY(:value))',
                [
                    'value' => $addId,
                    'addToTheseIds' => $this->convertHexArrayToByteArray($ids),
                ],
                [
                    'addToTheseIds' => Connection::PARAM_STR_ARRAY,
                ]
            );
        });
    }

    /**
     * sets all ids in onlyAddThisExistingIds as exclusion in promotion with id
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    private function updateJSON(string $id, array $onlyAddThisExistingIds): void
    {
        $value = '[]';
        if (count($onlyAddThisExistingIds) > 0) {
            $value = json_encode($onlyAddThisExistingIds);
        }

        $query = new RetryableQuery(
            $this->connection->prepare('UPDATE promotion SET promotion.exclusion_ids=:value WHERE id=:id')
        );

        $query->execute([
            'value' => $value,
            'id' => Uuid::fromHexToBytes($id),
        ]);
    }

    /**
     * function returns all promotion id hex values that are in given array ids
     */
    private function getExistingIds(array $ids): array
    {
        $sqlStatement = 'SELECT HEX(id) as uuid FROM promotion WHERE id IN (:ids)';

        $bytes = $this->convertHexArrayToByteArray($ids);

        $params = ['ids' => $bytes];

        $type = ['ids' => Connection::PARAM_STR_ARRAY];

        $rows = $this->connection
            ->executeQuery($sqlStatement, $params, $type)
            ->fetchAll(FetchMode::ASSOCIATIVE);

        $results = [];
        foreach ($rows as $row) {
            $results[] = $row['uuid'];
        }

        return $results;
    }

    /**
     * returns exclusions of promotion with id id
     *
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    private function getExclusionIds(string $id): array
    {
        if (!Uuid::isValid($id)) {
            return [];
        }

        $query = $this->connection->createQueryBuilder();
        $query->select('ifnull(exclusion_ids,JSON_ARRAY()) as exclusion_ids');
        $query->from(PromotionDefinition::ENTITY_NAME);
        $query->andWhere($query->expr()->eq('id', ':id'));

        $query->setParameter('id', Uuid::fromHexToBytes($id));

        return $query->execute()->fetchAll();
    }

    /**
     * helper function to convert hex array values to a binary array
     */
    private function convertHexArrayToByteArray(array $hexIds): array
    {
        if (count($hexIds) === 0) {
            return [];
        }

        //$hexIds = array_map('strtolower', $hexIds);

        $validValues = array_values(array_filter($hexIds, function ($hexId) {
            return Uuid::isValid((string) $hexId);
        }));

        if (count($validValues) === 0) {
            return [];
        }

        $bytes = array_map(function (string $id) {
            return Uuid::fromHexToBytes($id);
        }, $validValues);

        return $bytes;
    }

    private function addTags(array &$tags, array $addTags): void
    {
        foreach ($addTags as $tag) {
            $tag = $this->cacheKeyGenerator->getEntityTag($tag, PromotionDefinition::ENTITY_NAME);

            if (isset($tags[$tag])) {
                continue;
            }
            $tags[$tag] = $tag;
        }
    }
}
