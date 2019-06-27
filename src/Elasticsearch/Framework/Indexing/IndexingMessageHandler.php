<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Elasticsearch\Client;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
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

        /** @var EntitySearchResult $entities */
        $entities = $context->disableCache(function (Context $context) use ($repository, $criteria) {
            $context->setConsiderInheritance(true);

            return $repository->search($criteria, $context);
        });

        $toRemove = array_filter($ids, function (string $id) use ($entities) {
            return !$entities->has($id);
        });

        $documents = $this->createDocuments($definition, $entities);

        foreach ($toRemove as $id) {
            $documents[] = ['delete' => ['_id' => $id]];
        }

        // index found entities
        $this->client->bulk([
            'index' => $index,
            'type' => $definition->getEntityDefinition()->getEntityName(),
            'body' => $documents,
        ]);
    }

    private function createDocuments(AbstractElasticsearchDefinition $definition, iterable $entities): array
    {
        $documents = [];

        /** @var Entity $entity */
        foreach ($entities as $entity) {
            $documents[] = ['index' => ['_id' => $entity->getUniqueIdentifier()]];

            $document = json_decode(json_encode($entity, JSON_PRESERVE_ZERO_FRACTION), true);

            $fullText = $definition->buildFullText($entity);

            $document['fullText'] = $fullText->getFullText();
            $document['fullTextBoosted'] = $fullText->getBoosted();

            $documents[] = $document;
        }

        return $documents;
    }
}
