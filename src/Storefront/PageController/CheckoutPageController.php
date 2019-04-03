<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Storefront\AccountService;
use Shopware\Core\Checkout\Order\Storefront\OrderService;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\InvalidParameterException;

class CheckoutPageController extends StorefrontController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var CheckoutCartPageLoader|PageLoaderInterface
     */
    private $cartPageLoader;

    /**
     * @var CheckoutConfirmPageLoader|PageLoaderInterface
     */
    private $confirmPageLoader;

    /**
     * @var CheckoutFinishPageLoader|PageLoaderInterface
     */
    private $finishPageLoader;

    /**
     * @var CheckoutRegisterPageLoader|PageLoaderInterface
     */
    private $registerPageLoader;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct(
        CartService $cartService,
        PageLoaderInterface $cartPageLoader,
        PageLoaderInterface $confirmPageLoader,
        PageLoaderInterface $finishPageLoader,
        PageLoaderInterface $registerPageLoader,
        AccountService $accountService,
        OrderService $orderService
    ) {
        $this->cartService = $cartService;
        $this->cartPageLoader = $cartPageLoader;
        $this->confirmPageLoader = $confirmPageLoader;
        $this->finishPageLoader = $finishPageLoader;
        $this->registerPageLoader = $registerPageLoader;
        $this->accountService = $accountService;
        $this->orderService = $orderService;
    }

    /**
     * @Route("/checkout", name="frontend.checkout.forward", options={"seo"="false"}, methods={"GET"})
     */
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('frontend.checkout.cart.page');
    }

    /**
     * @Route("/checkout/cart", name="frontend.checkout.cart.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function cart(InternalRequest $request, CheckoutContext $context): Response
    {
        $page = $this->cartPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/checkout/cart/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/confirm", name="frontend.checkout.confirm.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function confirm(InternalRequest $request, CheckoutContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $page = $this->confirmPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/checkout/confirm/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/finish", name="frontend.checkout.finish.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws OrderNotFoundException
     * @throws InvalidParameterException
     * @throws MissingRequestParameterException
     */
    public function finish(InternalRequest $request, CheckoutContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        $page = $this->finishPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/checkout/finish/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/finish", name="frontend.checkout.finish.order", options={"seo"="false"}, methods={"POST"})
     */
    public function finishOrder(RequestDataBag $data, CheckoutContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        try {
            $orderId = $this->orderService->createOrder($data, $context);

            return new RedirectResponse($this->generateUrl('frontend.checkout.finish.page', [
                'orderId' => $orderId,
            ]));
        } catch (ConstraintViolationException $formViolations) {
        }

        return $this->forward('Shopware\Storefront\PageController\CheckoutPageController::confirm', ['formViolations' => $formViolations]);
    }

    /**
     * @Route("/checkout/register", name="frontend.checkout.register.page", options={"seo"="false"}, methods={"GET"})
     */
    public function register(Request $request, InternalRequest $internal, CheckoutContext $context): Response
    {
        /** @var string $redirect */
        $redirect = $request->get('redirectTo', $this->generateUrl('frontend.checkout.confirm.page'));

        if ($context->getCustomer()) {
            return $this->redirect($redirect);
        }

        $page = $this->registerPageLoader->load($internal, $context);

        // TODO change template NEXT-1930
        return $this->renderStorefront('@Storefront/page/account/register/index.html.twig', ['redirectTo' => $redirect, 'page' => $page]);
    }

    /**
     * @Route("/checkout/login", name="frontend.checkout.register.login", methods={"POST"})
     */
    public function loginCustomer(RequestDataBag $data, CheckoutContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.checkout.confirm.page');
        }

        try {
            $token = $this->accountService->loginWithPassword($data, $context);
            if (!empty($token)) {
                return new RedirectResponse($this->generateUrl('frontend.checkout.confirm.page'));
            }
        } catch (BadCredentialsException | UnauthorizedHttpException $e) {
        }

        return $this->forward('Shopware\Storefront\PageController\CheckoutPageController::register', [
            'loginError' => true,
        ]);
    }
}
