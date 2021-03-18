<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeEmailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangePasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractDeleteCustomerRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
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
     * @var AbstractChangeCustomerProfileRoute
     */
    private $changeCustomerProfileRoute;

    /**
     * @var AbstractChangePasswordRoute
     */
    private $changePasswordRoute;

    /**
     * @var AbstractChangeEmailRoute
     */
    private $changeEmailRoute;

    /**
     * @var AbstractDeleteCustomerRoute
     */
    private $deleteCustomerRoute;

    public function __construct(
        AccountOverviewPageLoader $overviewPageLoader,
        AccountProfilePageLoader $profilePageLoader,
        AbstractChangeCustomerProfileRoute $changeCustomerProfileRoute,
        AbstractChangePasswordRoute $changePasswordRoute,
        AbstractChangeEmailRoute $changeEmailRoute,
        AbstractDeleteCustomerRoute $deleteCustomerRoute
    ) {
        $this->overviewPageLoader = $overviewPageLoader;
        $this->profilePageLoader = $profilePageLoader;
        $this->changeCustomerProfileRoute = $changeCustomerProfileRoute;
        $this->changePasswordRoute = $changePasswordRoute;
        $this->changeEmailRoute = $changeEmailRoute;
        $this->deleteCustomerRoute = $deleteCustomerRoute;
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account", name="frontend.account.home.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function index(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $page = $this->overviewPageLoader->load($request, $context, $customer);

        return $this->renderStorefront('@Storefront/storefront/page/account/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/profile", name="frontend.account.profile.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function profileOverview(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->profilePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/index.html.twig', [
            'page' => $page,
            'passwordFormViolation' => $request->get('passwordFormViolation'),
            'emailFormViolation' => $request->get('emailFormViolation'),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/profile", name="frontend.account.profile.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function saveProfile(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        try {
            $this->changeCustomerProfileRoute->change($data, $context, $customer);

            $this->addFlash(self::SUCCESS, $this->trans('account.profileUpdateSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations]);
        } catch (\Exception $exception) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/profile/email", name="frontend.account.profile.email.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function saveEmail(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        try {
            $this->changeEmailRoute->change($data->get('email')->toRequestDataBag(), $context, $customer);

            $this->addFlash(self::SUCCESS, $this->trans('account.emailChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            $this->addFlash(self::DANGER, $this->trans('account.emailChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations, 'emailFormViolation' => true]);
        } catch (\Exception $exception) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/profile/password", name="frontend.account.profile.password.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function savePassword(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        try {
            $this->changePasswordRoute->change($data->get('password')->toRequestDataBag(), $context, $customer);

            $this->addFlash(self::SUCCESS, $this->trans('account.passwordChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations, 'passwordFormViolation' => true]);
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * @Since("6.3.3.0")
     * @LoginRequired()
     * @Route("/account/profile/delete", name="frontend.account.profile.delete", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function deleteProfile(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        try {
            $this->deleteCustomerRoute->delete($context, $customer);
            $this->addFlash(self::SUCCESS, $this->trans('account.profileDeleteSuccessAlert'));
        } catch (\Exception $exception) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        if ($request->get('redirectTo') || $request->get('forwardTo')) {
            return $this->createActionResponse($request);
        }

        return $this->redirectToRoute('frontend.home.page');
    }
}
