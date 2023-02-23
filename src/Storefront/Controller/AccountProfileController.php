<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeEmailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangePasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractDeleteCustomerRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedHook;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedHook;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
class AccountProfileController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountOverviewPageLoader $overviewPageLoader,
        private readonly AccountProfilePageLoader $profilePageLoader,
        private readonly AbstractChangeCustomerProfileRoute $changeCustomerProfileRoute,
        private readonly AbstractChangePasswordRoute $changePasswordRoute,
        private readonly AbstractChangeEmailRoute $changeEmailRoute,
        private readonly AbstractDeleteCustomerRoute $deleteCustomerRoute,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(path: '/account', name: 'frontend.account.home.page', defaults: ['_loginRequired' => true, '_noStore' => true], methods: ['GET'])]
    public function index(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $page = $this->overviewPageLoader->load($request, $context, $customer);

        $this->hook(new AccountOverviewPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/index.html.twig', ['page' => $page]);
    }

    #[Route(path: '/account/profile', name: 'frontend.account.profile.page', defaults: ['_loginRequired' => true, '_noStore' => true], methods: ['GET'])]
    public function profileOverview(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->profilePageLoader->load($request, $context);

        $this->hook(new AccountProfilePageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/profile/index.html.twig', [
            'page' => $page,
            'passwordFormViolation' => $request->get('passwordFormViolation'),
            'emailFormViolation' => $request->get('emailFormViolation'),
        ]);
    }

    #[Route(path: '/account/profile', name: 'frontend.account.profile.save', defaults: ['_loginRequired' => true], methods: ['POST'])]
    public function saveProfile(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        try {
            $this->changeCustomerProfileRoute->change($data, $context, $customer);

            $this->addFlash(self::SUCCESS, $this->trans('account.profileUpdateSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations]);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['e' => $exception]);
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    #[Route(path: '/account/profile/email', name: 'frontend.account.profile.email.save', defaults: ['_loginRequired' => true], methods: ['POST'])]
    public function saveEmail(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        try {
            $this->changeEmailRoute->change($data->get('email')->toRequestDataBag(), $context, $customer);

            $this->addFlash(self::SUCCESS, $this->trans('account.emailChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            $this->addFlash(self::DANGER, $this->trans('account.emailChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations, 'emailFormViolation' => true]);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['e' => $exception]);
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    #[Route(path: '/account/profile/password', name: 'frontend.account.profile.password.save', defaults: ['_loginRequired' => true], methods: ['POST'])]
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

    #[Route(path: '/account/profile/delete', name: 'frontend.account.profile.delete', defaults: ['_loginRequired' => true], methods: ['POST'])]
    public function deleteProfile(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        try {
            $this->deleteCustomerRoute->delete($context, $customer);
            $this->addFlash(self::SUCCESS, $this->trans('account.profileDeleteSuccessAlert'));
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['e' => $exception]);
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        if ($request->get('redirectTo') || $request->get('forwardTo')) {
            return $this->createActionResponse($request);
        }

        return $this->redirectToRoute('frontend.home.page');
    }
}
