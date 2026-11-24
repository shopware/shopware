<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Product visibilities and product media only have their own id as primary key.
 * Their change set carries the product of updated and deleted rows for the admin search.
 *
 * @internal
 */
#[Package('inventory')]
final readonly class ProductAssociationChangeSetSubscriber implements EventSubscriberInterface
{
    private const ENTITY_NAMES = [
        ProductVisibilityDefinition::ENTITY_NAME,
        ProductMediaDefinition::ENTITY_NAME,
    ];

    public function __construct(private AdminElasticsearchHelper $adminEsHelper)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'requestChangeSet',
        ];
    }

    public function requestChangeSet(PreWriteValidationEvent $event): void
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') || !$this->adminEsHelper->isEnabled()) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            // cascade deletes (e.g. of a sales channel) can remove thousands of rows, their products are not reindexed in the request
            if ($command instanceof CascadeDeleteCommand) {
                continue;
            }

            if ($command instanceof ChangeSetAware && \in_array($command->getEntityName(), self::ENTITY_NAMES, true)) {
                $command->requestChangeSet();
            }
        }
    }
}
