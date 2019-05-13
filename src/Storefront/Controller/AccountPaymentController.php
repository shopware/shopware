<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccountPaymentController extends StorefrontController
{
    /**
     * @var AccountPaymentMethodPageLoader|PageLoaderInterface
     */
    private $paymentMethodPageLoader;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        PageLoaderInterface $paymentMethodPageLoader,
        AccountService $accountService,
        TranslatorInterface $translator
    ) {
        $this->paymentMethodPageLoader = $paymentMethodPageLoader;
        $this->accountService = $accountService;
        $this->translator = $translator;
    }

    /**
     * @Route("/account/payment", name="frontend.account.payment.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function paymentOverview(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->paymentMethodPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/payment/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/payment", name="frontend.account.payment.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function savePayment(RequestDataBag $requestDataBag, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $paymentMethodId = $requestDataBag->getAlnum('paymentMethodId');

            $this->accountService->changeDefaultPaymentMethod(
                $paymentMethodId,
                $requestDataBag,
                $context->getCustomer(),
                $context
            );
        } catch (UnknownPaymentMethodException | InvalidUuidException $exception) {
            $this->addFlash('danger', $this->translator->trans('error.' . $exception->getErrorCode()));

            return $this->forward('Shopware\Storefront\PageController\AccountPageController::paymentOverview', ['success' => false]);
        }

        $this->addFlash('success', $this->translator->trans('account.paymentSuccess'));

        return new RedirectResponse($this->generateUrl('frontend.account.payment.page'));
    }
}
