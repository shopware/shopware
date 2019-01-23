<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ConditionTree\DataAbstractionLayer\Indexing\ConditionTypeNotFound;
use Shopware\Core\Content\ConditionTree\DataAbstractionLayer\Indexing\EventIdExtractorInterface;
use Shopware\Core\Content\ConditionTree\DataAbstractionLayer\Indexing\PayloadIndexer;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\ConditionTree\ConditionRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\Serializer;

class RulePayloadIndexer extends PayloadIndexer implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var TagAwareAdapter
     */
    private $cache;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractorInterface $eventIdExtractor,
        RepositoryInterface $repository,
        ConditionRegistry $conditionRegistry,
        Connection $connection,
        Serializer $serializer,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        TagAwareAdapter $cache
    ) {
        parent::__construct($eventDispatcher, $eventIdExtractor, $repository, $conditionRegistry);
        $this->connection = $connection;
        $this->serializer = $serializer;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
    }

    public static function getSubscribedEvents()
    {
        return [
            '/** TODO **/' => 'refreshPlugin',
        ];
    }

    public function refreshPlugin(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR, [
                    new NotFilter(
                        NotFilter::CONNECTION_AND,
                        [new EqualsAnyFilter('rule.conditions.type', $this->conditionRegistry->getNames())]
                    ),
                    new EqualsFilter('rule.invalid', true),
                ]
            )
        );

        $this->update($this->repository->searchIds($criteria, $context)->getIds());
    }

    protected function getEntityDescription(): string
    {
        return 'rule';
    }

    protected function update(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        if ($this->cache->hasItem('rules_key')) {
            $this->cache->deleteItem('rules_key');
        }

        $bytes = array_values(array_map(function ($id) { return Uuid::fromHexToBytes($id); }, $ids));

        $conditions = $this->connection->fetchAll(
            'SELECT rc.rule_id as array_key, rc.* FROM rule_condition rc  WHERE rc.rule_id IN (:ids) ORDER BY rc.rule_id',
            ['ids' => $bytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $rules = FetchModeHelper::group($conditions);

        $tags = [];
        foreach ($rules as $id => $rule) {
            $invalid = false;
            $serialized = null;
            try {
                $nested = $this->buildNested($rule, null);

                $tags[] = $this->cacheKeyGenerator->getEntityTag(Uuid::fromBytesToHex($id), RuleDefinition::class);

                //ensure the root rule is an AndRule
                $nested = new AndRule($nested);

                $serialized = $this->serializer->serialize($nested, 'json');
            } catch (ConditionTypeNotFound $exception) {
                $invalid = true;
            } finally {
                $this->connection->createQueryBuilder()
                    ->update('rule')
                    ->set('payload', ':serialize')
                    ->set('invalid', ':invalid')
                    ->where('id = :id')
                    ->setParameter('id', $id)
                    ->setParameter('serialize', $serialized)
                    ->setParameter('invalid', (int) $invalid)
                    ->execute();
            }
        }

        $this->cache->invalidateTags($tags);
    }
}
