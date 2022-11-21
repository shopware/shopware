<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Document;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.5.0 - Will be removed, using DocumentRoute instead to load generated document blob
 */
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
     * @var EntityRepository
     */
    private $documentRepository;

    /**
     * @internal
     */
    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        DocumentService $documentService,
        EntityRepository $documentRepository
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        if (!$salesChannelContext->getCustomer() && $request->get('deepLinkCode', false) === false) {
            throw CartException::customerNotLoggedIn();
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

        /** @var DocumentEntity|null $document */
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
