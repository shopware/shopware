<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class CustomFieldUpdater implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchOutdatedIndexDetector $indexDetector,
        private readonly Client $client,
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onNewCustomFieldCreated',
        ];
    }

    public function onNewCustomFieldCreated(EntityWrittenContainerEvent $containerEvent): void
    {
        $event = $containerEvent->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);

        if ($event === null) {
            return;
        }

        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        $newCreatedFields = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $existence = $writeResult->getExistence();

            if ($existence && $existence->exists()) {
                continue;
            }

            /** @var array<mixed> $esType */
            $esType = self::getTypeFromCustomFieldType($writeResult->getProperty('type'));

            $newCreatedFields[(string) $writeResult->getProperty('name')] = $esType;
        }

        if (\count($newCreatedFields) === 0) {
            return;
        }

        $this->createNewFieldsInIndices($newCreatedFields);
    }

    /**
     * @return array<mixed>
     */
    public static function getTypeFromCustomFieldType(string $type): array
    {
        return match ($type) {
            CustomFieldTypes::INT => [
                'type' => 'long',
            ],
            CustomFieldTypes::FLOAT => [
                'type' => 'double',
            ],
            CustomFieldTypes::BOOL => [
                'type' => 'boolean',
            ],
            CustomFieldTypes::DATETIME => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                'ignore_malformed' => true,
            ],
            CustomFieldTypes::PRICE, CustomFieldTypes::JSON => [
                'type' => 'object',
                'dynamic' => true,
            ],
            CustomFieldTypes::HTML, CustomFieldTypes::TEXT => [
                'type' => 'text',
            ],
            default => [
                'type' => 'keyword',
            ],
        };
    }

    /**
     * @param array<string, array<mixed>> $newCreatedFields
     */
    private function createNewFieldsInIndices(array $newCreatedFields): void
    {
        $indices = $this->indexDetector->getAllUsedIndices();

        $enabledMultilingualIndex = $this->elasticsearchHelper->enabledMultilingualIndex();
        $languageIds = $enabledMultilingualIndex ? $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM language') : [];

        foreach ($indices as $indexName) {
            // Check if index is old language based index
            $isLanguageBasedIndex = true;

            $body = [
                'properties' => [
                    'customFields' => [
                        'properties' => [],
                    ],
                ],
            ];

            // @deprecated tag:v6.6.0 - Remove this check as old language based indexes will be removed
            foreach ($languageIds as $languageId) {
                if (str_contains($indexName, $languageId)) {
                    $isLanguageBasedIndex = true;

                    break;
                }

                $isLanguageBasedIndex = false;
            }

            if ($isLanguageBasedIndex) {
                $body['properties']['customFields']['properties'] = $newCreatedFields;
            } else {
                foreach ($languageIds as $languageId) {
                    $body['properties']['customFields']['properties'][$languageId] = [
                        'type' => 'object',
                        'dynamic' => true,
                        'properties' => $newCreatedFields,
                    ];
                }
            }

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
