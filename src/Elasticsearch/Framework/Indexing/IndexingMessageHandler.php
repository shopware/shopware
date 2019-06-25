<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Elasticsearch\Client;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;

class IndexingMessageHandler extends AbstractMessageHandler
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var ElasticsearchRegistry
     */
    private $registry;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $entityRegistry;

    public function __construct(
        Client $client,
        ElasticsearchRegistry $registry,
        DefinitionInstanceRegistry $entityRegistry
    ) {
        $this->client = $client;
        $this->registry = $registry;
        $this->entityRegistry = $entityRegistry;
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
        $definition = $this->registry->get($entityName);

        if (!$definition) {
            throw new \RuntimeException(sprintf('Entity %s has no registered elasticsearch definition', $entityName));
        }

        $repository = $this->entityRegistry->getRepository($entityName);

        $criteria = new Criteria($ids);

        $definition->extendCriteria($criteria);

        $entities = $context->disableCache(function (Context $context) use ($repository, $criteria) {
            $context->setConsiderInheritance(true);

            return $repository->search($criteria, $context);
        });

        /** @var EntitySearchResult $entities */
        if (empty($entities->getIds())) {
            return;
        }

        $this->client->bulk([
            'index' => $index,
            'type' => $definition->getEntityDefinition()->getEntityName(),
            'body' => $this->createDocuments($entities),
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
