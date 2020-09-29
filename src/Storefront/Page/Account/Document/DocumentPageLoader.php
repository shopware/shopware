<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Document;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class DocumentPageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var DocumentService
     */
    private $documentService;

    /**
     * @var EntityRepositoryInterface
     */
    private $documentRepository;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        DocumentService $documentService,
        EntityRepositoryInterface $documentRepository
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->documentService = $documentService;
        $this->documentRepository = $documentRepository;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidDocumentException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): DocumentPage
    {
        if (!$salesChannelContext->getCustomer() && $request->get('deepLinkCode', false) === false) {
            throw new CustomerNotLoggedInException();
        }

        if ($request->get('documentId', false) === false) {
            throw new MissingRequestParameterException('documentId');
        }

        $documentId = $request->get('documentId');

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('id', $documentId),
            new EqualsFilter('deepLinkCode', $request->get('deepLinkCode')),
        ]));
        $criteria->addAssociation('documentMediaFile');
        $criteria->addAssociation('documentType');

        $document = $this->documentRepository->search($criteria, $salesChannelContext->getContext())->get($documentId);

        if (!$document) {
            throw new InvalidDocumentException($documentId);
        }

        $generatedDocument = $this->documentService->getDocument($document, $salesChannelContext->getContext());

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = DocumentPage::createFrom($page);

        $page->setDocument($generatedDocument);
        $page->setDeepLinkCode($request->get('deepLinkCode'));

        $this->eventDispatcher->dispatch(
            new DocumentPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}
