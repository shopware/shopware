<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Document\SalesChannel\AbstractDocumentRoute;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @internal
 */
class DocumentController extends StorefrontController
{
    private AbstractDocumentRoute $documentRoute;

    /**
     * @internal
     */
    public function __construct(AbstractDocumentRoute $documentRoute)
    {
        $this->documentRoute = $documentRoute;
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/account/order/document/{documentId}/{deepLinkCode}", name="frontend.account.order.single.document", methods={"GET"}, defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     */
    public function downloadDocument(Request $request, SalesChannelContext $context, string $documentId): Response
    {
        return $this->documentRoute->download($documentId, $request, $context, $request->get('deepLinkCode'));
    }
}
