<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Content\Shared\MailFlow\DocumentResolver;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\A11yRenderedDocumentAware;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-type A11yDocument array{documentId: string, deepLinkCode: string, fileExtension: string}
 */
#[Package('after-sales')]
class A11yRenderedDocumentStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<DocumentCollection> $documentRepository
     */
    public function __construct(
        private readonly EntityRepository $documentRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly DocumentResolver $documentResolver,
        private readonly DocumentFileResolver $documentFileResolver
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
        $ids = $this->resolveDocumentIds($storableFlow);

        if ($ids === []) {
            return [];
        }

        return $this->loadA11yDocuments(new Criteria($ids), $storableFlow->getContext());
    }

    /**
     * The configured file formats only decide what gets attached; the accessible html is linked in the
     * mail body for every document of the mail, so only the resolved document ids matter here.
     *
     * @return array<string>
     */
    private function resolveDocumentIds(StorableFlow $storableFlow): array
    {
        $stored = $storableFlow->getStore(A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS);
        $orderId = $storableFlow->getData(OrderAware::ORDER_ID);

        return array_keys($this->documentResolver->resolve(
            $storableFlow->getConfig(),
            \is_array($stored) ? array_values($stored) : [],
            \is_string($orderId) && $orderId !== '' ? $orderId : null,
            $storableFlow->getContext(),
        ));
    }

    /**
     * @return A11yDocument[]
     */
    private function loadA11yDocuments(Criteria $criteria, Context $context): array
    {
        $criteria->addAssociation('documentA11yMediaFile');
        $criteria->addAssociation('documentFiles.media');

        if (!Feature::isActive('v6.8.0.0')) {
            $event = new BeforeLoadStorableFlowDataEvent(
                DocumentDefinition::ENTITY_NAME,
                $criteria,
                $context,
            );
        } else {
            $event = new MailFlowDataCriteriaEvent(
                DocumentDefinition::ENTITY_NAME,
                $criteria,
                $context,
            );
        }

        $this->dispatcher->dispatch($event, $event->getName());

        $documents = $this->documentRepository
            ->search($criteria, $context)
            ->getEntities();

        $a11yDocuments = [];
        foreach ($documents as $document) {
            $resolved = $this->documentFileResolver->resolve($document, DocumentFormat::HTML->value);

            if ($resolved === null) {
                continue;
            }

            $a11yDocuments[] = [
                'documentId' => $document->getId(),
                'deepLinkCode' => $document->getDeepLinkCode(),
                'fileExtension' => $resolved->fileExtension,
            ];
        }

        return $a11yDocuments;
    }
}
