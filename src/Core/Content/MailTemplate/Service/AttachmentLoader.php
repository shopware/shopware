<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Content\MailTemplate\Service\Event\AttachmentLoaderCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AttachmentLoader
{
    private EntityRepository $documentRepository;

    private DocumentGenerator $documentGenerator;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $documentRepository,
        DocumentGenerator $documentGenerator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->documentRepository = $documentRepository;
        $this->documentGenerator = $documentGenerator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array<string> $documentIds
     *
     * @return array<array<string, string>>
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

        /** @var DocumentEntity $document */
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
