<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
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

    private DocumentGenerator $documentGenerator;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $documentRepository,
        DocumentGenerator $documentGenerator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->documentRepository = $documentRepository;
        $this->documentGenerator = $documentGenerator;
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
            $document = $this->documentGenerator->readDocument($document->getId(), $context);

            if ($document === null) {
                continue;
            }

            $attachments[] = [
                'content' => $document->getContent(),
                'fileName' => $document->getName(),
                'mimeType' => $document->getContentType(),
            ];
        }

        return $attachments;
    }
}
