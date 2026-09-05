<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Subscriber;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\DocumentV2\Event\DocumentDeletedEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\Clock;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed.
 *
 * @internal
 */
#[Package('after-sales')]
class DocumentDeleteSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<DocumentCollection> $documentRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $documentRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
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

        /** @var list<string> $ids */
        $ids = $event->getIds(DocumentDefinition::ENTITY_NAME);

        if ($ids === []) {
            return;
        }

        $this->checkForDependentDocuments($ids, $context);

        $criteria = new Criteria($ids);
        $criteria->addAssociation('documentFiles');
        $documents = $this->documentRepository->search($criteria, $context)->getEntities();

        $mediaIds = [];
        $deletedDocuments = [];

        foreach ($documents as $document) {
            // Legacy documents have a single media file and an optional accessibility media file
            // We keep this logic for backward compatibility
            if ($mediaId = $document->getDocumentMediaFileId()) {
                $mediaIds[] = ['id' => $mediaId];
            }

            if ($mediaId = $document->getDocumentA11yMediaFileId()) {
                $mediaIds[] = ['id' => $mediaId];
            }

            // DocumentV2-generated documents
            foreach ($document->getDocumentFiles() ?? [] as $documentFile) {
                $mediaIds[] = ['id' => $documentFile->getMediaId()];
            }

            $deletedDocuments[] = [
                'id' => $document->getId(),
                'orderId' => $document->getOrderId(),
                'orderVersionId' => $document->getOrderVersionId(),
                'documentNumber' => $document->getDocumentNumber() ?? '',
            ];
        }

        if ($mediaIds !== []) {
            $event->addSuccess(
                function () use ($mediaIds, $context): void {
                    $this->mediaRepository->delete(
                        $mediaIds,
                        $context,
                    );
                }
            );
        }

        $event->addSuccess(
            function () use ($deletedDocuments, $context): void {
                $deletedAt = Clock::get()->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

                foreach ($deletedDocuments as $deletedDocument) {
                    $this->eventDispatcher->dispatch(new DocumentDeletedEvent(
                        $deletedDocument['id'],
                        $deletedDocument['orderId'],
                        $deletedDocument['orderVersionId'],
                        $deletedDocument['documentNumber'],
                        $deletedAt,
                        $context,
                    ));
                }
            }
        );
    }

    /**
     * @param list<string> $ids
     */
    private function checkForDependentDocuments(array $ids, Context $context): void
    {
        $criteria = new Criteria();
        $criteria
            ->addAssociation('documentType')
            ->addFilter(new EqualsAnyFilter('referencedDocumentId', $ids))
            ->addFilter(new NotEqualsAnyFilter('id', $ids));

        $dependentDocuments = $this->documentRepository->search($criteria, $context)->getEntities();

        if ($dependentDocuments->count() === 0) {
            return;
        }

        $dependentDocumentInformations = array_values(array_map(
            function (DocumentEntity $document) {
                $id = $document->getId();
                $type = $document->getDocumentType()?->getTechnicalName() ?? 'unknown';
                $number = $document->getDocumentNumber() ?? 'unknown';

                return \sprintf('%s %s (%s)', $type, $number, $id);
            },
            $dependentDocuments->getElements()
        ));

        throw DocumentException::documentHasDependentDocuments($dependentDocumentInformations);
    }
}
