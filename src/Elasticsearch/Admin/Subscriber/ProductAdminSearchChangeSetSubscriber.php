<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Requests the change set of deleted or reassigned product visibilities and product media.
 * Their write results carry no product id then, `ProductAdminSearchIndexer` reads it from the change set.
 *
 * @internal
 */
#[Package('inventory')]
final readonly class ProductAdminSearchChangeSetSubscriber implements EventSubscriberInterface
{
    /**
     * storage name of the field that is part of the admin product document, per entity
     */
    private const INDEXED_FIELDS = [
        ProductVisibilityDefinition::ENTITY_NAME => 'sales_channel_id',
        ProductMediaDefinition::ENTITY_NAME => 'media_id',
    ];

    public function __construct(private AdminElasticsearchHelper $adminEsHelper)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'requestChangeSets',
        ];
    }

    public function requestChangeSets(EntityWriteEvent $event): void
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') || !$this->adminEsHelper->isEnabled()) {
            return;
        }

        foreach (self::INDEXED_FIELDS as $entity => $field) {
            foreach ($event->getCommandsForEntity($entity) as $command) {
                if ($command instanceof DeleteCommand) {
                    $command->requestChangeSet();

                    continue;
                }

                if ($command instanceof UpdateCommand && $command->hasField($field) && !$command->hasField('product_id')) {
                    $command->requestChangeSet();
                }
            }
        }
    }
}
