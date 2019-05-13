<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends StorefrontController
{
    /**
     * @var AccountLoginPageLoader|PageLoaderInterface
     */
    private $loginPageLoader;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var AccountRegistrationService
     */
    private $accountRegistrationService;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var CheckoutRegisterPageLoader|PageLoaderInterface
     */
    private $registerPageLoader;

    public function __construct(
        PageLoaderInterface $loginPageLoader,
        AccountService $accountService,
        AccountRegistrationService $accountRegistrationService,
        CartService $cartService,
        PageLoaderInterface $registerPageLoader
    ) {
        $this->loginPageLoader = $loginPageLoader;
        $this->accountService = $accountService;
        $this->accountRegistrationService = $accountRegistrationService;
        $this->cartService = $cartService;
        $this->registerPageLoader = $registerPageLoader;
    }

    /**
     * @Route("/account/register", name="frontend.account.register.page", methods={"GET"})
     */
    public function accountRegisterPage(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer() && $context->getCustomer()->getGuest()) {
            return $this->redirectToRoute('frontend.account.logout.page');
        }

        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        $redirect = $request->query->get('redirectTo', 'frontend.account.home.page');

        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->get('redirectParameters', json_encode([])),
            'page' => $page,
            'data' => $data,
        ]);
    }

    /**
     * @Route("/checkout/register", name="frontend.checkout.register.page", options={"seo"="false"}, methods={"GET"})
     */
    public function checkoutRegisterPage(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        /** @var string $redirect */
        $redirect = $request->get('redirectTo', 'frontend.checkout.confirm.page');

        if ($context->getCustomer()) {
            return $this->redirectToRoute($redirect);
        }

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $page = $this->registerPageLoader->load($request, $context);

        return $this->renderStorefront(
            '@Storefront/page/checkout/address/index.html.twig',
            ['redirectTo' => $redirect, 'page' => $page, 'data' => $data]
        );
    }

    /**
     * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
     */
    public function register(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        try {
            if (!$data->has('differentShippingAddress')) {
                $data->remove('shippingAddress');
            }

            $this->accountRegistrationService->register($data, $data->has('guest'), $context);
        } catch (ConstraintViolationException $formViolations) {
            if (!$request->request->has('errorRoute')) {
                throw new MissingRequestParameterException('errorRoute');
            }
            $errorRoute = $request->get('errorRoute');

            // this is to show the correct form because we have different usecases (account/register||checkout/register)
            $formUrl = $this->generateUrl($errorRoute);
            $router = $this->container->get('router');
            $router->getContext()->setMethod(Request::METHOD_GET);
            $formRoute = $router->match($formUrl);

            $forwardController = $formRoute['_controller'];

            return $this->forward($forwardController, ['formViolations' => $formViolations]);
        }

        $this->accountService->login($data->get('email'), $context, $data->has('guest'));

        return $this->createActionResponse($request);
    }
}
