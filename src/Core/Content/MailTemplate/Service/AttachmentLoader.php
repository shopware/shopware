<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Content\MailTemplate\Service\Event\AttachmentLoaderCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal (flag: FEATURE_NEXT_7530)
 */
class AttachmentLoader
{
    private EntityRepositoryInterface $documentRepository;

    private DocumentService $documentService;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $documentRepository,
        DocumentService $documentService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->documentRepository = $documentRepository;
        $this->documentService = $documentService;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(array $documentIds, Context $context): array
    {
        $attachments = [];
        $criteria = new Criteria($documentIds);
        $criteria->addAssociation('documentMediaFile');
        $criteria->addAssociation('documentType');

        $criteriaEvent = new AttachmentLoaderCriteriaEvent($criteria);
        $this->eventDispatcher->dispatch($criteriaEvent);

        $entities = $this->documentRepository->search($criteria, $context);

        foreach ($entities as $document) {
            $document = $this->documentService->getDocument($document, $context);

            $attachments[] = [
                'content' => $document->getFileBlob(),
                'fileName' => $document->getFilename(),
                'mimeType' => $document->getContentType(),
            ];
        }

        return $attachments;
    }
}
