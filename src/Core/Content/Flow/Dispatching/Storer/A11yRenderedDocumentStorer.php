<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\A11yRenderedDocumentAware;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-type A11yDocument array{documentId: string, deepLinkCode: string, fileExtension: string|null}
 */
#[Package('after-sales')]
class A11yRenderedDocumentStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $documentRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof A11yRenderedDocumentAware || isset($stored[A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS])) {
            return $stored;
        }

        $stored[A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS] = $event->getA11yDocumentIds();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS)) {
            return;
        }

        $storable->setData(A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS, $storable->getStore(A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS));

        $storable->lazy(
            A11yRenderedDocumentAware::A11Y_DOCUMENTS,
            $this->lazyLoad(...)
        );
    }

    /**
     * @return A11yDocument[]
     */
    private function lazyLoad(StorableFlow $storableFlow): array
    {
        $ids = $storableFlow->getStore(A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS);
        if (!\is_array($ids) || empty($ids)) {
            return [];
        }

        $criteria = new Criteria($ids);

        return $this->loadA11yDocuments($criteria, $storableFlow->getContext());
    }

    /**
     * @return A11yDocument[]
     */
    private function loadA11yDocuments(Criteria $criteria, Context $context): array
    {
        $criteria->addAssociation('documentA11yMediaFile');

        $event = new BeforeLoadStorableFlowDataEvent(
            DocumentDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        /** @var DocumentCollection $documents */
        $documents = $this->documentRepository
            ->search($criteria, $context)
            ->getEntities();

        $a11yDocuments = [];
        foreach ($documents as $document) {
            if ($document->getDocumentA11yMediaFile() === null) {
                continue;
            }

            $a11yDocuments[] = [
                'documentId' => $document->getId(),
                'deepLinkCode' => $document->getDeepLinkCode(),
                'fileExtension' => $document->getDocumentA11yMediaFile()->getFileExtension(),
            ];
        }

        return $a11yDocuments;
    }
}
