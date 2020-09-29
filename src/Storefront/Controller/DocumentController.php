<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Document\DocumentPageLoader;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class DocumentController extends StorefrontController
{
    /**
     * @var DocumentPageLoader
     */
    protected $documentPageLoader;

    public function __construct(DocumentPageLoader $documentPageLoader)
    {
        $this->documentPageLoader = $documentPageLoader;
    }

    /**
     * @Route("/account/order/document/{documentId}/{deepLinkCode}", name="frontend.account.order.single.document", methods={"GET"})
     *
     * @internal (flag:FEATURE_NEXT_10537)
     *
     * @throws InvalidDocumentException
     */
    public function downloadDocument(Request $request, SalesChannelContext $context): Response
    {
        $download = $request->query->getBoolean('download', false);

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
