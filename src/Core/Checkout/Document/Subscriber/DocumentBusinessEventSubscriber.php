<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Subscriber;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\Event\DocumentDeletedEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches `after_sales.document.deleted`.
 *
 * Deletion has no domain service to dispatch from: documents are removed via plain DAL
 * deletes (the Admin API order-document grid, bulk edit, Sync API, and the order cascade
 * delete) — there is no `DocumentGenerator`-equivalent for removal. So the deletion
 * business moment can only be observed on the DAL delete, the same way the core dispatches
 * `checkout.customer.deleted` from `CustomerBeforeDeleteSubscriber`. The snapshot is read
 * before the row is gone (`beforeDelete`) and the event is dispatched only after the
 * delete commits (`addSuccess`).
 *
 * `after_sales.document.generated` is intentionally NOT dispatched here — it is dispatched
 * at its domain action, `DocumentGenerator::generate()`, where the rendered document and
 * its number are available as typed values.
 *
 * @todo If a dedicated document-deletion service is ever introduced, move this dispatch
 *       into that service so the deletion business moment is stated at the domain action
 *       instead of reconstructed from the DAL delete.
 *
 * @internal
 */
#[Package('after-sales')]
class DocumentBusinessEventSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<DocumentCollection> $documentRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $documentRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $context = $event->getContext();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        /** @var list<string> $ids */
        $ids = $event->getIds(DocumentDefinition::ENTITY_NAME);
        if ($ids === []) {
            return;
        }

        $documents = $this->documentRepository->search(new Criteria($ids), $context)->getEntities();
        if ($documents->count() === 0) {
            return;
        }

        $event->addSuccess(function () use ($documents, $context): void {
            $deletedAt = (new \DateTimeImmutable())->format(\DATE_ATOM);

            foreach ($documents as $document) {
                $this->eventDispatcher->dispatch(new DocumentDeletedEvent(
                    $context,
                    $document->getId(),
                    $document->getOrderId(),
                    $deletedAt,
                    $document->getDocumentNumber()
                ));
            }
        });
    }
}
