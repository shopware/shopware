<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Exception\CustomerAuthThrottledException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Document\SalesChannel\AbstractDocumentRoute;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('framework')]
class DocumentController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractDocumentRoute $documentRoute,
        private readonly AbstractLogoutRoute $logoutRoute
    ) {
    }

    #[Route(path: '/account/order/document/{documentId}/{deepLinkCode}', name: 'frontend.account.order.single.document', defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true], methods: ['GET'])]
    #[Route(path: '/account/order/document/{documentId}/{deepLinkCode}/{fileType}', name: 'frontend.account.order.single.document.a11y', defaults: ['_noStore' => true], methods: ['GET', 'POST'])]
    public function downloadDocument(Request $request, SalesChannelContext $context, string $documentId): Response
    {
        $fileType = $request->get('fileType', PdfRenderer::FILE_EXTENSION);

        try {
            return $this->documentRoute->download($documentId, $request, $context, $request->get('deepLinkCode'), $fileType);
        } catch (GuestNotAuthenticatedException|WrongGuestCredentialsException|CustomerAuthThrottledException $exception) {
            if ($context->getCustomer() !== null) {
                $this->logoutRoute->logout($context, new RequestDataBag([]));
            }

            return $this->redirectToRoute(
                'frontend.account.guest.login.page',
                [
                    'redirectTo' => 'frontend.account.order.single.document.a11y',
                    'redirectParameters' => [
                        'deepLinkCode' => $request->get('deepLinkCode'),
                        'documentId' => $documentId,
                        'fileType' => $fileType,
                    ],
                    'loginError' => ($exception instanceof WrongGuestCredentialsException),
                    'waitTime' => ($exception instanceof CustomerAuthThrottledException) ? $exception->getWaitTime() : '',
                ]
            );
        } catch (CustomerNotLoggedInException $exception) {
            if ($context->getCustomer() !== null) {
                $this->logoutRoute->logout($context, new RequestDataBag([]));
            }

            throw $exception;
        }
    }
}
