<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Elasticsearch\Client;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomFieldUpdater implements EventSubscriberInterface
{
    private ElasticsearchOutdatedIndexDetector $indexDetector;

    private Client $client;

    private ElasticsearchHelper $elasticsearchHelper;

    public function __construct(ElasticsearchOutdatedIndexDetector $indexDetector, Client $client, ElasticsearchHelper $elasticsearchHelper)
    {
        $this->indexDetector = $indexDetector;
        $this->client = $client;
        $this->elasticsearchHelper = $elasticsearchHelper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onNewCustomFieldCreated',
        ];
    }

    public function onNewCustomFieldCreated(EntityWrittenContainerEvent $containerEvent): void
    {
        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        $event = $containerEvent->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);

        if ($event === null) {
            return;
        }

        $newCreatedFields = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $existence = $writeResult->getExistence();

            if ($existence && $existence->exists()) {
                continue;
            }

            $esType = self::getTypeFromCustomFieldType($writeResult->getProperty('type'));

            if ($esType === null) {
                continue;
            }

            $newCreatedFields[$writeResult->getProperty('name')] = $esType;
        }

        if (\count($newCreatedFields) === 0) {
            return;
        }

        $this->createNewFieldsInIndices($newCreatedFields);
    }

    public static function getTypeFromCustomFieldType(string $type): ?array
    {
        switch ($type) {
            case CustomFieldTypes::INT:
                return [
                    'type' => 'long',
                ];
            case CustomFieldTypes::FLOAT:
                return [
                    'type' => 'double',
                ];
            case CustomFieldTypes::BOOL:
                return [
                    'type' => 'boolean',
                ];
            case CustomFieldTypes::DATETIME:
                return [
                    'type' => 'date',
                    'format' => 'yyyy-MM-dd HH:mm:ss.000',
                    'ignore_malformed' => true,
                ];
            case CustomFieldTypes::JSON:
                return [
                    'type' => 'object',
                    'dynamic' => true,
                ];
            case CustomFieldTypes::COLORPICKER:
            case CustomFieldTypes::ENTITY:
            case CustomFieldTypes::HTML:
            case CustomFieldTypes::MEDIA:
            case CustomFieldTypes::SELECT:
            case CustomFieldTypes::SWITCH:
            case CustomFieldTypes::TEXT:
            default:
                return [
                    'type' => 'keyword',
                ];
        }
    }

    private function createNewFieldsInIndices(array $newCreatedFields): void
    {
        $indices = $this->indexDetector->getAllUsedIndices();

        foreach ($indices as $indexName) {
            $body = [
                'properties' => [
                    'customFields' => [
                        'properties' => $newCreatedFields,
                    ],
                ],
            ];

            // For some reason, we need to include the includes to prevent merge conflicts.
            // This error can happen for example after updating from version <6.4.
            $current = $this->client->indices()->get(['index' => $indexName]);
            $includes = $current[$indexName]['mappings']['_source']['includes'] ?? [];
            if ($includes !== []) {
                $body['_source'] = [
                    'includes' => $includes,
                ];
            }

            $this->client->indices()->putMapping([
                'index' => $indexName,
                'body' => $body,
            ]);
        }
    }
}
