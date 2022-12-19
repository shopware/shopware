<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Document\SalesChannel\AbstractDocumentRoute;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Document\DocumentPageLoader;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package storefront
 *
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class DocumentController extends StorefrontController
{
    /**
     * @deprecated tag:v6.5.0 - Property $documentPageLoader will be removed due to unused
     */
    protected DocumentPageLoader $documentPageLoader;

    private AbstractDocumentRoute $documentRoute;

    /**
     * @internal
     */
    public function __construct(DocumentPageLoader $documentPageLoader, AbstractDocumentRoute $documentRoute)
    {
        $this->documentPageLoader = $documentPageLoader;
        $this->documentRoute = $documentRoute;
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/account/order/document/{documentId}/{deepLinkCode}", name="frontend.account.order.single.document", methods={"GET"}, defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     */
    public function downloadDocument(Request $request, SalesChannelContext $context): Response
    {
        if (Feature::isActive('v6.5.0.0')) {
            /**
             * @deprecated tag:v6.5.0 - $documentId request parameter will be injected as a third argument parameter
             */
            $documentId = $request->get('documentId', false);

            if ($documentId === false) {
                throw new MissingRequestParameterException('documentId');
            }

            return $this->documentRoute->download($documentId, $request, $context, $request->get('deepLinkCode'));
        }

        $download = $request->query->getBoolean('download');

        $documentPage = $this->documentPageLoader->load($request, $context);

        $generatedDocument = $documentPage->getDocument();

        return $this->createResponse(
            $generatedDocument->getFilename(),
            $generatedDocument->getFileBlob(),
            $download,
            $generatedDocument->getContentType()
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
}
