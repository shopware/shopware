<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
final class ProductDocumentChangeSetSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'requestChangeSet',
        ];
    }

    public function requestChangeSet(PreWriteValidationEvent $event): void
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            if ($command instanceof DeleteCommand && $command->getEntityName() === ProductDocumentDefinition::ENTITY_NAME) {
                $command->requestChangeSet();
            }
        }
    }
}
