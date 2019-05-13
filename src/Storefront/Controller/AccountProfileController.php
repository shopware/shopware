<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException as CustomerNotLoggedInExceptionAlias;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccountProfileController extends StorefrontController
{
    /**
     * @var AccountOverviewPageLoader|PageLoaderInterface
     */
    private $overviewPageLoader;

    /**
     * @var AccountProfilePageLoader|PageLoaderInterface
     */
    private $profilePageLoader;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        PageLoaderInterface $overviewPageLoader,
        PageLoaderInterface $profilePageLoader,
        AccountService $accountService,
        TranslatorInterface $translator
    ) {
        $this->overviewPageLoader = $overviewPageLoader;
        $this->profilePageLoader = $profilePageLoader;
        $this->accountService = $accountService;
        $this->translator = $translator;
    }

    /**
     * @Route("/account", name="frontend.account.home.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function index(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->overviewPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function profileOverview(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->profilePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/profile/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function saveProfile(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->accountService->saveProfile($data, $context);

            $this->addFlash('success', $this->translator->trans('account.profileUpdateSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations]);
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->translator->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }
}
