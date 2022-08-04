<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerAuthThrottledException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CustomerRecoveryHashExpiredException;
use Shopware\Core\Checkout\Customer\Exception\InactiveCustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Framework\Routing\Annotation\NoStore;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Account\Login\AccountGuestLoginPageLoadedHook;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoadedHook;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPage;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoadedHook;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class AuthController extends StorefrontController
{
    private AccountLoginPageLoader $loginPageLoader;

    private AbstractSendPasswordRecoveryMailRoute $sendPasswordRecoveryMailRoute;

    private AbstractResetPasswordRoute $resetPasswordRoute;

    private AbstractLoginRoute $loginRoute;

    private AbstractLogoutRoute $logoutRoute;

    private StorefrontCartFacade $cartFacade;

    private AccountRecoverPasswordPageLoader $recoverPasswordPageLoader;

    /**
     * @internal
     */
    public function __construct(
        AccountLoginPageLoader $loginPageLoader,
        AbstractSendPasswordRecoveryMailRoute $sendPasswordRecoveryMailRoute,
        AbstractResetPasswordRoute $resetPasswordRoute,
        AbstractLoginRoute $loginRoute,
        AbstractLogoutRoute $logoutRoute,
        StorefrontCartFacade $cartFacade,
        AccountRecoverPasswordPageLoader $recoverPasswordPageLoader
    ) {
        $this->loginPageLoader = $loginPageLoader;
        $this->sendPasswordRecoveryMailRoute = $sendPasswordRecoveryMailRoute;
        $this->resetPasswordRoute = $resetPasswordRoute;
        $this->loginRoute = $loginRoute;
        $this->logoutRoute = $logoutRoute;
        $this->cartFacade = $cartFacade;
        $this->recoverPasswordPageLoader = $recoverPasswordPageLoader;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
     * @NoStore
     */
    public function loginPage(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        /** @var string $redirect */
        $redirect = $request->get('redirectTo', 'frontend.account.home.page');

        $customer = $context->getCustomer();

        if ($customer !== null && $customer->getGuest() === false) {
            $request->request->set('redirectTo', $redirect);

            return $this->createActionResponse($request);
        }

        $page = $this->loginPageLoader->load($request, $context);

        $this->hook(new AccountLoginPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->get('redirectParameters', json_encode([])),
            'page' => $page,
            'loginError' => (bool) $request->get('loginError'),
            'waitTime' => $request->get('waitTime'),
            'errorSnippet' => $request->get('errorSnippet'),
            'data' => $data,
        ]);
    }

    /**
     * @Since("6.3.4.1")
     * @Route("/account/guest/login", name="frontend.account.guest.login.page", methods={"GET"})
     * @NoStore
     */
    public function guestLoginPage(Request $request, SalesChannelContext $context): Response
    {
        /** @var string $redirect */
        $redirect = $request->get('redirectTo', 'frontend.account.home.page');

        $customer = $context->getCustomer();

        if ($customer !== null) {
            $request->request->set('redirectTo', $redirect);

            return $this->createActionResponse($request);
        }

        $waitTime = (int) $request->get('waitTime');
        if ($waitTime) {
            $this->addFlash(self::INFO, $this->trans('account.loginThrottled', ['%seconds%' => $waitTime]));
        }

        if ((bool) $request->get('loginError')) {
            $this->addFlash(self::DANGER, $this->trans('account.orderGuestLoginWrongCredentials'));
        }

        $page = $this->loginPageLoader->load($request, $context);

        $this->hook(new AccountGuestLoginPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/guest-auth.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->get('redirectParameters', json_encode([])),
            'page' => $page,
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/account/logout", name="frontend.account.logout.page", methods={"GET"})
     */
    public function logout(Request $request, SalesChannelContext $context, RequestDataBag $dataBag): Response
    {
        if ($context->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $this->logoutRoute->logout($context, $dataBag);
            $this->addFlash(self::SUCCESS, $this->trans('account.logoutSucceeded'));

            $parameters = [];
        } catch (ConstraintViolationException $formViolations) {
            $parameters = ['formViolations' => $formViolations];
        }

        return $this->redirectToRoute('frontend.account.login.page', $parameters);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/account/login", name="frontend.account.login", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function login(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();

        if ($customer !== null && $customer->getGuest() === false) {
            return $this->createActionResponse($request);
        }

        try {
            $token = $this->loginRoute->login($data, $context)->getToken();
            if (!empty($token)) {
                $this->addCartErrors($this->cartFacade->get($token, $context));

                return $this->createActionResponse($request);
            }
        } catch (BadCredentialsException | UnauthorizedHttpException | InactiveCustomerException | CustomerAuthThrottledException $e) {
            if ($e instanceof InactiveCustomerException) {
                $errorSnippet = $e->getSnippetKey();
            }

            if ($e instanceof CustomerAuthThrottledException) {
                $waitTime = $e->getWaitTime();
            }
        }

        $data->set('password', null);

        return $this->forwardToRoute(
            'frontend.account.login.page',
            [
                'loginError' => true,
                'errorSnippet' => $errorSnippet ?? null,
                'waitTime' => $waitTime ?? null,
            ]
        );
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/account/recover", name="frontend.account.recover.page", methods={"GET"})
     */
    public function recoverAccountForm(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/recover-password.html.twig', [
            'page' => $page,
        ]);
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/account/recover", name="frontend.account.recover.request", methods={"POST"})
     */
    public function generateAccountRecovery(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        try {
            $data->get('email')
                ->set('storefrontUrl', $request->attributes->get(RequestTransformer::STOREFRONT_URL));

            $this->sendPasswordRecoveryMailRoute->sendRecoveryMail(
                $data->get('email')->toRequestDataBag(),
                $context,
                false
            );

            $this->addFlash(self::SUCCESS, $this->trans('account.recoveryMailSend'));
        } catch (CustomerNotFoundException $e) {
            $this->addFlash(self::SUCCESS, $this->trans('account.recoveryMailSend'));
        } catch (InconsistentCriteriaIdsException $e) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        } catch (RateLimitExceededException $e) {
            $this->addFlash(self::INFO, $this->trans('error.rateLimitExceeded', ['%seconds%' => $e->getWaitTime()]));
        }

        return $this->redirectToRoute('frontend.account.recover.page');
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/account/recover/password", name="frontend.account.recover.password.page", methods={"GET"})
     */
    public function resetPasswordForm(Request $request, SalesChannelContext $context): Response
    {
        /** @deprecated tag:v6.5.0 - call to loginPageLoader and $loginPage will be removed */
        $loginPage = null;
        if (!Feature::isActive('v6.5.0.0')) {
            $loginPage = $this->loginPageLoader->load($request, $context);
        }

        /** @var ?string $hash */
        $hash = $request->get('hash');

        if (!$hash || !\is_string($hash)) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        try {
            $page = $this->recoverPasswordPageLoader->load($request, $context, $hash);
        } catch (ConstraintViolationException $e) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        $this->hook(new AccountRecoverPasswordPageLoadedHook($page, $context));

        if ($page->getHash() === null || $page->isHashExpired()) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        if (Feature::isActive('v6.5.0.0')) {
            return $this->renderStorefront('@Storefront/storefront/page/account/profile/reset-password.html.twig', [
                'page' => $page,
                'formViolations' => $request->get('formViolations'),
            ]);
        }

        /** @deprecated tag:v6.5.0 - page will be instance of AccountRecoverPasswordPage and $hash will be moved to $page.getHash() */
        return $this->renderStorefront('@Storefront/storefront/page/account/profile/reset-password.html.twig', [
            'page' => $loginPage,
            'hash' => $hash,
            'formViolations' => $request->get('formViolations'),
        ]);
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/account/recover/password", name="frontend.account.recover.password.reset", methods={"POST"})
     */
    public function resetPassword(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $hash = $data->get('password')->get('hash');

        try {
            $pw = $data->get('password');

            $this->resetPasswordRoute->resetPassword($pw->toRequestDataBag(), $context);

            $this->addFlash(self::SUCCESS, $this->trans('account.passwordChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordChangeNoSuccess'));

            return $this->forwardToRoute(
                'frontend.account.recover.password.page',
                ['hash' => $hash, 'formViolations' => $formViolations, 'passwordFormViolation' => true]
            );
        } catch (CustomerNotFoundByHashException $e) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.recover.request');
        } catch (CustomerRecoveryHashExpiredException $e) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashExpired'));

            return $this->forwardToRoute('frontend.account.recover.request');
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }
}
