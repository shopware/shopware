<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Content\MailTemplate\Service\Event\AttachmentLoaderCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal (flag: FEATURE_NEXT_7530)
 */
class AttachmentLoader
{
    private EntityRepository $documentRepository;

    private DocumentGenerator $documentGenerator;

    private EventDispatcherInterface $eventDispatcher;

    private DocumentService $documentService;

    public function __construct(
        EntityRepository $documentRepository,
        DocumentGenerator $documentGenerator,
        DocumentService $documentService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->documentRepository = $documentRepository;
        $this->documentGenerator = $documentGenerator;
        $this->eventDispatcher = $eventDispatcher;
        $this->documentService = $documentService;
    }

    /**
     * @param array<string> $documentIds
     */
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
            if (Feature::isActive('v6.5.0.0')) {
                $document = $this->documentGenerator->readDocument($document->getId(), $context);

                if ($document === null) {
                    continue;
                }

                $attachments[] = [
                    'content' => $document->getContent(),
                    'fileName' => $document->getName(),
                    'mimeType' => $document->getContentType(),
                ];

                continue;
            }

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
