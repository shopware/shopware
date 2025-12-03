<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\SalesChannel;

use Shopware\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Document\Service\AbstractDocumentTypeRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('after-sales')]
final class DocumentRoute extends AbstractDocumentRoute
{
    public const ACCEPT_WILDCARD = '*/*';

    /**
     * @internal
     *
     * @param EntityRepository<DocumentCollection> $documentRepository
     * @param AbstractDocumentTypeRenderer[] $renderers
     */
    public function __construct(
        private readonly DocumentGenerator $documentGenerator,
        private readonly EntityRepository $documentRepository,
        private readonly GuestAuthenticator $guestAuthenticator,
        private readonly iterable $renderers,
    ) {
    }

    public function getDecorated(): AbstractDocumentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/document/download/{documentId}/{deepLinkCode}',
        name: 'store-api.document.download',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => DocumentDefinition::ENTITY_NAME]
    )]
    public function download(
        string $documentId,
        Request $request,
        SalesChannelContext $context,
        string $deepLinkCode = '',
        ?string $fileType = null
    ): Response {
        $this->checkAuth($documentId, $request, $context);

        $isGuest = $context->getCustomer() === null || $context->getCustomer()->getGuest();
        if ($isGuest && $deepLinkCode === '') {
            throw DocumentException::customerNotLoggedIn();
        }

        $download = $request->query->getBoolean('download');

        $supportedFormats = $this->getSupportedFileTypes();
        if ($fileType) {
            if (!Feature::isActive('v6.8.0.0')) {
                $document = $this->documentGenerator->readDocument(
                    $documentId,
                    $context->getContext(),
                    $deepLinkCode,
                    $fileType
                );

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

            if (!isset($supportedFormats[$fileType])) {
                throw DocumentException::documentFileTypeNotSupported($fileType);
            }
        }

        $this->registerFileTypes($supportedFormats, $request);
        $requestedFormats = $fileType ? [$fileType => $supportedFormats[$fileType] ?? $fileType] : $this->getRequestedFileTypes($request);

        $supportedRequestedFormats = array_filter(
            $requestedFormats,
            fn (string $fileType) => isset($supportedFormats[$fileType]),
            \ARRAY_FILTER_USE_KEY
        );

        if (!$fileType && !$supportedRequestedFormats) {
            throw DocumentException::documentAcceptHeaderMimeTypesNotSupported(
                array_values($requestedFormats),
                array_values($supportedFormats)
            );
        }

        $document = null;

        foreach ($supportedRequestedFormats as $supportedFileType => $supportedMimeType) {
            $document = $this->documentGenerator->readDocument(
                $documentId,
                $context->getContext(),
                $deepLinkCode,
                $supportedFileType
            );

            if ($document !== null) {
                break;
            }
        }

        if ($document === null) {
            if (!Feature::isActive('v6.8.0.0')) {
                /*
                 * this response code needs to be removed also in the api-schema-docs:
                 * src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/paths/document.json
                 */
                return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
            }

            throw DocumentException::documentFileTypeUnavailable($documentId, array_keys($supportedRequestedFormats));
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

    private function checkAuth(string $documentId, Request $request, SalesChannelContext $context): void
    {
        $criteria = (new Criteria([$documentId]))
            ->addAssociations(['order.orderCustomer.customer', 'order.billingAddress']);

        $document = $this->documentRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$document) {
            throw DocumentException::documentNotFound($documentId);
        }

        $order = $document->getOrder();
        if (!$order) {
            throw DocumentException::orderNotFound($document->getOrderId());
        }

        $orderCustomer = $order->getOrderCustomer();
        if (!$orderCustomer) {
            throw DocumentException::customerNotLoggedIn();
        }

        if ($orderCustomer->getCustomerId() === $context->getCustomer()?->getId()) {
            return;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            // feature flag due to different exceptions
            Feature::silent('v6.8.0.0', fn () => $this->checkGuestAuth($order, $orderCustomer, $request));

            return;
        }

        $this->guestAuthenticator->validate($order, $request);
    }

    /**
     * @deprecated tag:v6.8.0 - was replaced by GuestAuthenticator::validateGuestAuthentication
     */
    private function checkGuestAuth(
        OrderEntity $order,
        OrderCustomerEntity $orderCustomer,
        Request $request
    ): void {
        $isOrderByGuest = $orderCustomer->getCustomer() !== null && $orderCustomer->getCustomer()->getGuest();

        if (!$isOrderByGuest) {
            throw DocumentException::customerNotLoggedIn();
        }

        // Verify email and zip code with this order
        if ($request->get('email', false) && $request->get('zipcode', false)) {
            $billingAddress = $order->getBillingAddress();
            if ($billingAddress === null
                || strtolower($request->get('email')) !== strtolower($orderCustomer->getEmail())
                || strtoupper($request->get('zipcode')) !== strtoupper($billingAddress->getZipcode() ?: '')) {
                throw DocumentException::wrongGuestCredentials();
            }
        } else {
            throw DocumentException::guestNotAuthenticated();
        }
    }

    /**
     * @return array<string>
     *
     * returns a fileType => mimeType mapping
     */
    private function getSupportedFileTypes(): array
    {
        $supportedFileTypes = [];
        $renderers = $this->renderers instanceof \Traversable ? iterator_to_array($this->renderers) : $this->renderers;

        foreach ($renderers as $fileType => $renderer) {
            $supportedFileTypes[$fileType] = $renderer->getContentType();
        }

        /*
         * Zugferd xml is not rendered by a file renderer
         * its generated in the document renderer itself
         * therefor it's not registered by document_type.renderer key="xml" and needs to be done manually
         *
         */
        $supportedFileTypes[ZugferdRenderer::FILE_EXTENSION] = ZugferdRenderer::FILE_CONTENT_TYPE;

        return $supportedFileTypes;
    }

    /**
     * @param array<string> $supportedFileTypes
     */
    private function registerFileTypes(array $supportedFileTypes, Request $request): void
    {
        foreach ($supportedFileTypes as $fileType => $mimeType) {
            $request->setFormat($fileType, [$mimeType]);
        }
    }

    /**
     * @return array<string>
     */
    private function getRequestedFileTypes(Request $request): array
    {
        $requestedFileTypes = $request->getAcceptableContentTypes();
        $fileTypes = [];

        if (empty($requestedFileTypes)) {
            return $this->getDefaultFileTypes();
        }

        foreach ($requestedFileTypes as $mimeType) {
            if ($mimeType === self::ACCEPT_WILDCARD) {
                return $this->getDefaultFileTypes();
            }

            if ($fileType = $request->getFormat($mimeType)) {
                $fileTypes[$fileType] = $mimeType;
            } else {
                // keep unmapped mime type for exception output
                $fileTypes[$mimeType] = $mimeType;
            }
        }

        return $fileTypes;
    }

    /**
     * @return array<string>
     */
    private function getDefaultFileTypes(): array
    {
        return [PdfRenderer::FILE_EXTENSION => PdfRenderer::FILE_CONTENT_TYPE];
    }
}
