<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;

/**
 * @final
 */
#[Package('inventory')]
class PromotionAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<PromotionCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $factory,
        private readonly EntityRepository $repository,
        private readonly ElasticsearchFieldBuilder $fieldBuilder,
        private readonly int $indexingBatchSize
    ) {
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getEntity(): string
    {
        return PromotionDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'promotion-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $promotionIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'active',
            'code',
            'validFrom',
            'validUntil',
        ]);

        $translations = $event->getPrimaryKeysWithPropertyChange(PromotionTranslationDefinition::ENTITY_NAME, [
            'name',
        ]);

        foreach ($translations as $pks) {
            if (isset($pks['promotionId'])) {
                $promotionIds[] = $pks['promotionId'];
            }
        }

        return array_values(array_unique(array_filter($promotionIds, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $languageFields = $this->fieldBuilder->translated(AbstractElasticsearchDefinition::KEYWORD_FIELD);

        $override = [
            'active' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'code' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'name' => $languageFields,
            'validFrom' => ElasticsearchFieldBuilder::datetime(),
            'validUntil' => ElasticsearchFieldBuilder::datetime(),
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
        ];

        $mapping['properties'] ??= [];
        $mapping['properties'] = array_merge($mapping['properties'], $override);

        return $mapping;
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    /**
     * @return array<string, array{id:string, text:string}>
     */
    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(promotion.id)) as id,
                   GROUP_CONCAT(DISTINCT promotion_translation.name SEPARATOR " ") as name,
                   JSON_ARRAYAGG(JSON_OBJECT(
                       'languageId', LOWER(HEX(promotion_translation.language_id)),
                       'name', promotion_translation.name
                   )) as translatedNames,
                   promotion.code AS code,
                   promotion.active AS active,
                   promotion.valid_from AS validFrom,
                   promotion.valid_until AS validUntil,
                   promotion.created_at as createdAt
            FROM promotion
                INNER JOIN promotion_translation
                    ON promotion.id = promotion_translation.promotion_id
            WHERE promotion.id IN (:ids)
            GROUP BY promotion.id
SQL,
            [
                'ids' => Uuid::fromHexToBytesList($ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $id = (string) $row['id'];
            $text = \implode(' ', array_filter([$row['name'] ?? '', $row['code'] ?? '', $id]));

            if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                $mapped[$id] = [
                    'id' => $id,
                    'text' => \strtolower($text),
                ];

                continue;
            }

            $translatedNames = $this->decodeTranslatedValues((string) $row['translatedNames']);

            $mapped[$id] = [
                'id' => $id,
                'text' => \strtolower($text),
                'name' => $translatedNames,
                'code' => $row['code'] ?? null,
                'active' => (bool) $row['active'],
                'validFrom' => $this->formatDateTime($row, 'validFrom'),
                'validUntil' => $this->formatDateTime($row, 'validUntil'),
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
            ];
        }

        return $mapped;
    }
}
