<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class CustomFieldSearchableSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onCustomFieldWritten',
        ];
    }

    public function onCustomFieldWritten(EntityWrittenContainerEvent $containerEvent): void
    {
        if ($this->parameterBag->has('elasticsearch.enabled') && $this->parameterBag->get('elasticsearch.enabled')) {
            return;
        }

        $customFieldWrittenEvent = $containerEvent->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);

        if ($customFieldWrittenEvent === null) {
            return;
        }

        $customFieldIds = [];
        foreach ($customFieldWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();

            if (!\array_key_exists('includeInSearch', $payload) || $payload['includeInSearch'] !== false) {
                continue;
            }

            $customFieldIds[] = $writeResult->getPrimaryKey();
        }

        if ($customFieldIds === []) {
            return;
        }

        $this->handleProductSearchConfig($customFieldIds);
    }

    /**
     * @param array<string> $customFieldIds
     */
    private function handleProductSearchConfig(array $customFieldIds): void
    {
        $this->connection->executeStatement(
            'DELETE FROM product_search_config_field
            WHERE custom_field_id IN (:customFieldIds)',
            ['customFieldIds' => Uuid::fromHexToBytesList($customFieldIds)],
            ['customFieldIds' => ArrayParameterType::BINARY]
        );
    }
}
