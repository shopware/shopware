<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('after-sales')]
final class DocumentRoute extends AbstractDocumentRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<DocumentCollection> $documentRepository
     */
    public function __construct(
        private readonly DocumentGenerator $documentGenerator,
        private readonly EntityRepository $documentRepository,
    ) {
    }

    public function getDecorated(): AbstractDocumentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.7.0 - Parameter $fileType will be added - reason:new-optional-parameter
     */
    #[Route(path: '/store-api/document/download/{documentId}/{deepLinkCode}', name: 'store-api.document.download', methods: ['GET', 'POST'], defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true, '_entity' => 'document'])]
    public function download(
        string $documentId,
        Request $request,
        SalesChannelContext $context,
        string $deepLinkCode = '',
        /* , string $fileType = PdfRenderer::FILE_EXTENSION */
    ): Response {
        $fileType = \func_get_args()[4] ?? PdfRenderer::FILE_EXTENSION;

        if (!$context->getCustomer()) {
            $this->checkGuestAuth($documentId, $request, $context->getContext());
        }

        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();
        if ($customer->getGuest() && $deepLinkCode === '') {
            throw DocumentException::customerNotLoggedIn();
        }

        $download = $request->query->getBoolean('download');

        $document = $this->documentGenerator->readDocument($documentId, $context->getContext(), $deepLinkCode, $fileType);

        if ($document === null) {
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

        return $this->createResponse(
            $document->getName(),
            $document->getContent(),
            $download,
            $document->getContentType()
        );
    }

    private function createResponse(string $filename, string $content, bool $forceDownload, string $contentType): Response
    {
        $response = new Response($content);

        $disposition = HeaderUtils::makeDisposition(
            $forceDownload ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $filename,
            // only printable ascii
            preg_replace('/[\x00-\x1F\x7F-\xFF]/', '_', $filename) ?? ''
        );

        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    private function checkGuestAuth(string $documentId, Request $request, Context $context): void
    {
        $criteria = new Criteria([$documentId]);
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('order.billingAddress');

        $document = $this->documentRepository->search($criteria, $context)->first();
        if (!$document instanceof DocumentEntity) {
            throw DocumentException::documentNotFound($documentId);
        }

        $order = $document->getOrder();
        if ($order === null) {
            throw DocumentException::guestNotAuthenticated();
        }

        $orderCustomer = $order->getOrderCustomer();
        if ($orderCustomer === null) {
            throw DocumentException::customerNotLoggedIn();
        }

        $guest = $orderCustomer->getCustomer() !== null && $orderCustomer->getCustomer()->getGuest();
        // Throw exception when customer is not guest
        if (!$guest) {
            throw DocumentException::customerNotLoggedIn();
        }

        // Verify email and zip code with this order
        if ($request->get('email', false) && $request->get('zipcode', false)) {
            $billingAddress = $order->getBillingAddress();
            if ($billingAddress === null
                || $request->get('email') !== $orderCustomer->getEmail()
                || $request->get('zipcode') !== $billingAddress->getZipcode()) {
                throw DocumentException::wrongGuestCredentials();
            }
        } else {
            throw DocumentException::guestNotAuthenticated();
        }
    }
}
