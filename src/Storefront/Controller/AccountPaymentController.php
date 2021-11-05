<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangePaymentMethodRoute;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\Annotation\NoStore;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class AccountPaymentController extends StorefrontController
{
    private AccountPaymentMethodPageLoader $paymentMethodPageLoader;

    private AbstractChangePaymentMethodRoute $changePaymentMethodRoute;

    public function __construct(
        AccountPaymentMethodPageLoader $paymentMethodPageLoader,
        AbstractChangePaymentMethodRoute $changePaymentMethodRoute
    ) {
        $this->paymentMethodPageLoader = $paymentMethodPageLoader;
        $this->changePaymentMethodRoute = $changePaymentMethodRoute;
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/payment", name="frontend.account.payment.page", options={"seo"="false"}, methods={"GET"})
     * @NoStore
     *
     * @throws CustomerNotLoggedInException
     */
    public function paymentOverview(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->paymentMethodPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/payment/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/payment", name="frontend.account.payment.save", methods={"POST"})
     */
    public function savePayment(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        try {
            $paymentMethodId = $requestDataBag->getAlnum('paymentMethodId');

            $this->changePaymentMethodRoute->change(
                $paymentMethodId,
                $requestDataBag,
                $context,
                $customer
            );
        } catch (UnknownPaymentMethodException | InvalidUuidException $exception) {
            $this->addFlash(self::DANGER, $this->trans('error.' . $exception->getErrorCode()));

            return $this->forwardToRoute('frontend.account.payment.page', ['success' => false]);
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.paymentSuccess'));

        return new RedirectResponse($this->generateUrl('frontend.account.payment.page'));
    }
}
