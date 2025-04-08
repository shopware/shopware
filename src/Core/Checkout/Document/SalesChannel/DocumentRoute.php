<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\SalesChannel;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
final class DocumentRoute extends AbstractDocumentRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DocumentGenerator $documentGenerator,
        private readonly EntityRepository $documentRepository
    ) {
    }

    public function getDecorated(): AbstractDocumentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/document/download/{documentId}/{deepLinkCode}', name: 'store-api.document.download', methods: ['GET', 'POST'], defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true, '_entity' => 'document'])]
    public function download(string $documentId, Request $request, SalesChannelContext $context, string $deepLinkCode = ''): Response
    {
        $this->checkAuth($documentId, $context);

        if ($context->getCustomer() === null || ($context->getCustomer()->getGuest() && $deepLinkCode === '')) {
            throw CartException::customerNotLoggedIn();
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

    private function checkAuth(string $documentId, SalesChannelContext $context): void
    {
        $criteria = (new Criteria([$documentId]))
            ->addAssociations(['order.orderCustomer.customer', 'order.billingAddress']);

        $document = $this->documentRepository->search($criteria, $context->getContext())->first();
        if (!$document) {
            throw CartException::insufficientPermission();
        }

        $order = $document->getOrder();
        if (!$order) {
            throw CartException::insufficientPermission();
        }

        $orderCustomer = $order->getOrderCustomer();
        if (!$orderCustomer) {
            throw CartException::insufficientPermission();
        }

        if ($context->getCustomer() !== null && $orderCustomer->getCustomerId() !== $context->getCustomer()->getId()) {
            throw CartException::insufficientPermission();
        }
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
