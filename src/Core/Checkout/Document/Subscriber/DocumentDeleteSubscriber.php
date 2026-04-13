<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Subscriber;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
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
        $documents = $this->documentRepository->search($criteria, $context)->getEntities();

        $mediaIds = [];
        foreach ($documents as $document) {
            if ($mediaId = $document->getDocumentMediaFileId()) {
                $mediaIds[] = ['id' => $mediaId];
            }

            if ($mediaId = $document->getDocumentA11yMediaFileId()) {
                $mediaIds[] = ['id' => $mediaId];
            }
        }

        if ($mediaIds === []) {
            return;
        }

        $event->addSuccess(
            function () use ($mediaIds, $context): void {
                $this->mediaRepository->delete(
                    $mediaIds,
                    $context,
                );
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
