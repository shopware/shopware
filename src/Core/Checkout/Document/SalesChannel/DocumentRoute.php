<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
final class DocumentRoute extends AbstractDocumentRoute
{
    private DocumentGenerator $documentGenerator;

    /**
     * @internal
     */
    public function __construct(DocumentGenerator $documentGenerator)
    {
        $this->documentGenerator = $documentGenerator;
    }

    public function getDecorated(): AbstractDocumentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.4.12.0")
     * @Entity("document")
     * @OA\Post(
     *      path="/document/download",
     *      summary="Download generated document",
     *      description="Returns blob file of a generated document to download.",
     *      operationId="download",
     *      tags={"Store API", "Document"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Returns the document information and blob to download.",
     *          @OA\JsonContent(ref="#/components/schemas/Document")
     *     )
     * )
     * @Route("/store-api/document/download/{documentId}/{deepLinkCode}", name="store-api.document.download", methods={"GET", "POST"}, defaults={"_acl"={"document.viewer"}, "_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     */
    public function download(string $documentId, Request $request, SalesChannelContext $context, string $deepLinkCode = ''): Response
    {
        if ($context->getCustomer() === null || ($context->getCustomer()->getGuest() && $deepLinkCode === '')) {
            throw new CustomerNotLoggedInException();
        }

        $download = $request->query->getBoolean('download');

        $document = $this->documentGenerator->readDocument($documentId, $context->getContext(), $deepLinkCode);

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
}
