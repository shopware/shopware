<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class AccountProfileController extends StorefrontController
{
    /**
     * @var AccountOverviewPageLoader
     */
    private $overviewPageLoader;

    /**
     * @var AccountProfilePageLoader
     */
    private $profilePageLoader;

    /**
     * @var AccountService
     */
    private $accountService;

    public function __construct(
        AccountOverviewPageLoader $overviewPageLoader,
        AccountProfilePageLoader $profilePageLoader,
        AccountService $accountService
    ) {
        $this->overviewPageLoader = $overviewPageLoader;
        $this->profilePageLoader = $profilePageLoader;
        $this->accountService = $accountService;
    }

    /**
     * @Route("/account", name="frontend.account.home.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function index(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->overviewPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function profileOverview(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->profilePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/index.html.twig', [
            'page' => $page,
            'passwordFormViolation' => $request->get('passwordFormViolation'),
            'emailFormViolation' => $request->get('emailFormViolation'),
        ]);
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function saveProfile(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->accountService->saveProfile($data, $context);

            $this->addFlash('success', $this->trans('account.profileUpdateSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations]);
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * @Route("/account/profile/email", name="frontend.account.profile.email.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function saveEmail(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->accountService->saveEmail($data->get('email'), $context);

            $this->addFlash('success', $this->trans('account.emailChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            $this->addFlash('danger', $this->trans('account.emailChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations, 'emailFormViolation' => true]);
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * @Route("/account/profile/password", name="frontend.account.profile.password.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function savePassword(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->accountService->savePassword($data->get('password'), $context);

            $this->addFlash('success', $this->trans('account.passwordChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            $this->addFlash('danger', $this->trans('account.passwordChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations, 'passwordFormViolation' => true]);
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }
}
