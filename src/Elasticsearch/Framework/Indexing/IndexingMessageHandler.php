<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Elasticsearch\Client;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Elasticsearch\Framework\Event\CreateIndexingCriteriaEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class IndexingMessageHandler extends AbstractMessageHandler
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        Client $client,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->client = $client;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getHandledMessages(): iterable
    {
        return [
            IndexingMessage::class,
        ];
    }

    public function handle($msg): void
    {
        /* @var IndexingMessage $msg */
        $this->indexEntities(
            $msg->getIndex(),
            $msg->getIds(),
            $msg->getEntityName(),
            $msg->getContext()
        );
    }

    private function indexEntities(string $index, array $ids, string $entityName, Context $context): void
    {
        $repository = $this->definitionRegistry->getRepository($entityName);

        $definition = $repository->getDefinition();

        if (!$repository instanceof EntityRepository) {
            throw new \RuntimeException('Expected entity repository for service: ' . $entityName . '.repository');
        }

        $criteria = new Criteria($ids);

        $this->eventDispatcher->dispatch(
            new CreateIndexingCriteriaEvent($definition, $criteria, $context)
        );

        $entities = $context->disableCache(function (Context $context) use ($repository, $criteria) {
            $context->setConsiderInheritance(true);

            return $repository->search($criteria, $context);
        });

        /** @var EntitySearchResult $entities */
        if (empty($entities->getIds())) {
            return;
        }

        $documents = $this->createDocuments($entities);

        $this->client->bulk([
            'index' => $index,
            'type' => $definition->getEntityName(),
            'body' => $documents,
        ]);
    }

    private function createDocuments(iterable $entities): array
    {
        $documents = [];

        /** @var Entity $entity */
        foreach ($entities as $entity) {
            $documents[] = ['index' => ['_id' => $entity->getUniqueIdentifier()]];
            $documents[] = json_decode(json_encode($entity, JSON_PRESERVE_ZERO_FRACTION), true);
        }

        return $documents;
    }
}
