<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CustomerRecoveryHashExpiredException;
use Shopware\Core\Checkout\Customer\Exception\InactiveCustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class AuthController extends StorefrontController
{
    /**
     * @var AccountLoginPageLoader
     */
    private $loginPageLoader;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRecoveryRepository;

    /**
     * @var AbstractSendPasswordRecoveryMailRoute
     */
    private $sendPasswordRecoveryMailRoute;

    /**
     * @var AbstractResetPasswordRoute
     */
    private $resetPasswordRoute;

    /**
     * @var AbstractLoginRoute
     */
    private $loginRoute;

    /**
     * @var AbstractLogoutRoute
     */
    private $logoutRoute;

    /**
     * @var SystemConfigService
     */
    private $systemConfig;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(
        AccountLoginPageLoader $loginPageLoader,
        EntityRepositoryInterface $customerRecoveryRepository,
        AbstractSendPasswordRecoveryMailRoute $sendPasswordRecoveryMailRoute,
        AbstractResetPasswordRoute $resetPasswordRoute,
        AbstractLoginRoute $loginRoute,
        SystemConfigService $systemConfig,
        AbstractLogoutRoute $logoutRoute,
        CartService $cartService
    ) {
        $this->loginPageLoader = $loginPageLoader;
        $this->customerRecoveryRepository = $customerRecoveryRepository;
        $this->sendPasswordRecoveryMailRoute = $sendPasswordRecoveryMailRoute;
        $this->resetPasswordRoute = $resetPasswordRoute;
        $this->loginRoute = $loginRoute;
        $this->logoutRoute = $logoutRoute;
        $this->systemConfig = $systemConfig;
        $this->cartService = $cartService;
    }

    /**
     * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
     */
    public function loginPage(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        /** @var string $redirect */
        $redirect = $request->get('redirectTo', 'frontend.account.home.page');

        if ($context->getCustomer()) {
            $request->request->set('redirectTo', $redirect);

            return $this->createActionResponse($request);
        }

        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->get('redirectParameters', json_encode([])),
            'page' => $page,
            'loginError' => (bool) $request->get('loginError'),
            'errorSnippet' => $request->get('errorSnippet'),
            'data' => $data,
        ]);
    }

    /**
     * @Route("/account/logout", name="frontend.account.logout.page", methods={"GET"})
     */
    public function logout(Request $request, SalesChannelContext $context): Response
    {
        if ($context->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $this->logoutRoute->logout($context);
            $salesChannelId = $context->getSalesChannel()->getId();
            if ($request->hasSession() && $this->systemConfig->get('core.loginRegistration.invalidateSessionOnLogOut', $salesChannelId)) {
                $request->getSession()->invalidate();
            }

            $this->addFlash('success', $this->trans('account.logoutSucceeded'));

            $parameters = [];
        } catch (ConstraintViolationException $formViolations) {
            $parameters = ['formViolations' => $formViolations];
        }

        return $this->redirectToRoute('frontend.account.login.page', $parameters);
    }

    /**
     * @Route("/account/login", name="frontend.account.login", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function login(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->createActionResponse($request);
        }

        try {
            $token = $this->loginRoute->login($data, $context)->getToken();
            if (!empty($token)) {
                if (Feature::isActive('FEATURE_NEXT_10058')) {
                    $this->addCartErrors($this->cartService->getCart($token, $context));
                }

                return $this->createActionResponse($request);
            }
        } catch (BadCredentialsException | UnauthorizedHttpException | InactiveCustomerException $e) {
            if ($e instanceof InactiveCustomerException) {
                $errorSnippet = $e->getSnippetKey();
            }
        }

        $data->set('password', null);

        return $this->forwardToRoute(
            'frontend.account.login.page',
            [
                'loginError' => true,
                'errorSnippet' => $errorSnippet ?? null,
            ]
        );
    }

    /**
     * @Route("/account/recover", name="frontend.account.recover.page", methods={"GET"})
     *
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function recoverAccountForm(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/recover-password.html.twig', [
            'page' => $page,
        ]);
    }

    /**
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

            $this->addFlash('success', $this->trans('account.recoveryMailSend'));
        } catch (CustomerNotFoundException $e) {
            $this->addFlash('success', $this->trans('account.recoveryMailSend'));
        } catch (InconsistentCriteriaIdsException $e) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.account.recover.page');
    }

    /**
     * @Route("/account/recover/password", name="frontend.account.recover.password.page", methods={"GET"})
     *
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function resetPasswordForm(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->loginPageLoader->load($request, $context);
        $hash = $request->get('hash');

        if (!$hash) {
            $this->addFlash('danger', $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        $customerHashCriteria = new Criteria();
        $customerHashCriteria->addFilter(new EqualsFilter('hash', $hash));

        $customerRecovery = $this->customerRecoveryRepository
            ->search($customerHashCriteria, $context->getContext())
            ->first();

        if ($customerRecovery === null) {
            $this->addFlash('danger', $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        if (!$this->checkHash($hash, $context->getContext())) {
            $this->addFlash('danger', $this->trans('account.passwordHashExpired'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/reset-password.html.twig', [
            'page' => $page,
            'hash' => $hash,
            'formViolations' => $request->get('formViolations'),
        ]);
    }

    /**
     * @Route("/account/recover/password", name="frontend.account.recover.password.reset", methods={"POST"})
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function resetPassword(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $hash = $data->get('password')->get('hash');

        try {
            $pw = $data->get('password');

            $this->resetPasswordRoute->resetPassword($pw->toRequestDataBag(), $context);

            $this->addFlash('success', $this->trans('account.passwordChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            $this->addFlash('danger', $this->trans('account.passwordChangeNoSuccess'));

            return $this->forwardToRoute(
                'frontend.account.recover.password.page',
                ['hash' => $hash, 'formViolations' => $formViolations, 'passwordFormViolation' => true]
            );
        } catch (CustomerNotFoundByHashException $e) {
            $this->addFlash('danger', $this->trans('account.passwordChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.recover.request');
        } catch (CustomerRecoveryHashExpiredException $e) {
            $this->addFlash('danger', $this->trans('account.passwordHashExpired'));

            return $this->forwardToRoute('frontend.account.recover.request');
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    private function checkHash(string $hash, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('hash', $hash));

        $recovery = $this->customerRecoveryRepository->search($criteria, $context)->first();

        $validDateTime = (new \DateTime())->sub(new \DateInterval('PT2H'));

        return $recovery && $validDateTime < $recovery->getCreatedAt();
    }
}
