<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException as CustomerNotLoggedInExceptionAlias;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthController extends StorefrontController
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
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(PageLoaderInterface $loginPageLoader, AccountService $accountService, TranslatorInterface $translator)
    {
        $this->loginPageLoader = $loginPageLoader;
        $this->accountService = $accountService;
        $this->translator = $translator;
    }

    /**
     * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
     */
    public function loginPage(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        /** @var string $redirect */
        $redirect = $request->get('redirectTo', 'frontend.account.home.page');

        if ($context->getCustomer()) {
            return $this->createActionResponse($request);
        }

        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->get('redirectParameters', json_encode([])),
            'page' => $page,
            'loginError' => (bool) $request->get('loginError'),
            'data' => $data,
        ]);
    }

    /**
     * @Route("/account/logout", name="frontend.account.logout.page", methods={"GET"})
     */
    public function logout(SalesChannelContext $context): Response
    {
        if ($context->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $this->accountService->logout($context);

            $this->addFlash('success', $this->translator->trans('account.logoutSucceeded'));

            $parameters = [];
        } catch (ConstraintViolationException $formViolations) {
            $parameters = ['formViolations' => $formViolations];
        }

        return $this->redirectToRoute('frontend.account.login.page', $parameters);
    }

    /**
     * @Route("/account/login", name="frontend.account.login", methods={"POST"})
     */
    public function login(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->createActionResponse($request);
        }

        try {
            $token = $this->accountService->loginWithPassword($data, $context);
            if (!empty($token)) {
                return $this->createActionResponse($request);
            }
        } catch (BadCredentialsException | UnauthorizedHttpException $e) {
        }

        $data->set('password', null);

        return $this->forward('Shopware\Storefront\PageController\AccountPageController::login', [
            'loginError' => true,
        ]);
    }

    /**
     * @Route("/account/profile/email", name="frontend.account.profile.email.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function saveEmail(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->accountService->saveEmail($data->get('email'), $context);

            $this->addFlash('success', $this->translator->trans('account.emailChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forward(__CLASS__ . '::profileOverview', ['formViolations' => $formViolations]);
        } catch (\Exception $exception) {
            $this->addFlash('error', $this->translator->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * @Route("/account/profile/password", name="frontend.account.profile.password.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function savePassword(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->accountService->savePassword($data->get('password'), $context);
        } catch (ConstraintViolationException $formViolations) {
            return $this->forward(__CLASS__ . '::profileOverview', ['formViolations' => $formViolations]);
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }
}
