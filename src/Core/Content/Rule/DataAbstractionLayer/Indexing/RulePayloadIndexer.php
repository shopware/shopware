<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\ContainerInterface;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RulePayloadIndexer implements IndexerInterface, EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var RuleConditionRegistry
     */
    private $ruleConditionRegistry;

    /**
     * @var CacheClearer
     */
    private $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var RuleDefinition
     */
    private $ruleDefinition;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        RuleConditionRegistry $ruleConditionRegistry,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        CacheClearer $cache,
        IteratorFactory $iteratorFactory,
        RuleDefinition $ruleDefinition
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->ruleConditionRegistry = $ruleConditionRegistry;
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->iteratorFactory = $iteratorFactory;
        $this->ruleDefinition = $ruleDefinition;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostInstallEvent::class => 'refreshPlugin',
            PluginPostActivateEvent::class => 'refreshPlugin',
            PluginPostUpdateEvent::class => 'refreshPlugin',
            PluginPostDeactivateEvent::class => 'refreshPlugin',
            PluginPostUninstallEvent::class => 'refreshPlugin',
        ];
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $iterator = $this->iteratorFactory->createIterator($this->ruleDefinition);

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent('Start indexing rules', $iterator->fetchCount()),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->update($ids);
            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(\count($ids)),
                ProgressAdvancedEvent::NAME
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent('Finished indexing rules'),
            ProgressFinishedEvent::NAME
        );
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $iterator = $this->iteratorFactory->createIterator($this->ruleDefinition, $lastId);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }
        $this->update($ids);

        return $iterator->getOffset();
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = [];

        $nested = $event->getEventByEntityName(RuleDefinition::ENTITY_NAME);
        if ($nested) {
            $ids = $nested->getIds();
        }

        $this->update($ids);
    }

    public function refreshPlugin(): void
    {
        // Delete the payload and invalid flag of all rules
        $this->connection->update('rule', ['payload' => null, 'invalid' => 0], [1 => 1]);

        $this->clearCache();
    }

    public function update(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $bytes = array_values(array_map(function ($id) { return Uuid::fromHexToBytes($id); }, $ids));

        $conditions = $this->connection->fetchAll(
            'SELECT rc.rule_id as array_key, rc.* FROM rule_condition rc  WHERE rc.rule_id IN (:ids) ORDER BY rc.rule_id',
            ['ids' => $bytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $rules = FetchModeHelper::group($conditions);

        $tags = [];
        $updated = [];
        foreach ($rules as $id => $rule) {
            $invalid = false;
            $serialized = null;

            try {
                $nested = $this->buildNested($rule, null);

                $tags[] = $this->cacheKeyGenerator->getEntityTag(Uuid::fromBytesToHex($id), $this->ruleDefinition);

                //ensure the root rule is an AndRule
                $nested = new AndRule($nested);

                $serialized = serialize($nested);
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

                $updated[Uuid::fromBytesToHex($id)] = ['payload' => $serialized, 'invalid' => $invalid];
            }
        }

        $this->cache->invalidateTags($tags);

        $this->cache->deleteItems([CartRuleLoader::CHECKOUT_RULE_LOADER_CACHE_KEY]);

        return $updated;
    }

    public static function getName(): string
    {
        return 'Swag.RulePayloadIndexer';
    }

    private function clearCache(): void
    {
        $this->cache->invalidateTags(['entity_' . $this->ruleDefinition->getEntityName()]);
    }

    private function buildNested(array $rules, ?string $parentId): array
    {
        $nested = [];
        foreach ($rules as $rule) {
            if ($rule['parent_id'] !== $parentId) {
                continue;
            }

            if (!$this->ruleConditionRegistry->has($rule['type'])) {
                throw new ConditionTypeNotFound($rule['type']);
            }

            $ruleClass = $this->ruleConditionRegistry->getRuleClass($rule['type']);
            $object = new $ruleClass();

            if ($rule['value'] !== null) {
                /* @var Rule $object */
                $object->assign(json_decode($rule['value'], true));
            }

            if ($object instanceof ContainerInterface) {
                $children = $this->buildNested($rules, $rule['id']);
                foreach ($children as $child) {
                    $object->addRule($child);
                }
            }

            $nested[] = $object;
        }

        return $nested;
    }
}
