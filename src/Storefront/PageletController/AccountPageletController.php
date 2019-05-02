<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionService;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccountPageletController extends StorefrontController
{
    /**
     * @var NewsletterSubscriptionServiceInterface
     */
    private $newsletterSubscriptionService;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var AccountService
     */
    private $accountService;

    public function __construct(
        NewsletterSubscriptionServiceInterface $newsletterSubscriptionService,
        AccountService $accountService,
        TranslatorInterface $translator
    ) {
        $this->newsletterSubscriptionService = $newsletterSubscriptionService;
        $this->translator = $translator;
        $this->accountService = $accountService;
    }

    /**
     * @Route(path="/widgets/account/newsletter", name="widgets.account.newsletter", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function newsletter(Request $request, RequestDataBag $dataBag, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        /** @var bool $subscribed */
        $subscribed = (bool) ($request->get('option', false) === NewsletterSubscriptionService::STATUS_DIRECT);

        if (!$subscribed) {
            $dataBag->set('option', 'unsubscribe');
        }

        $messages = [];
        $success = null;

        if ($subscribed) {
            try {
                $this->newsletterSubscriptionService->subscribe($this->hydrateFromCustomer($dataBag, $context->getCustomer()), $context);

                $this->accountService->setNewsletterFlag($context->getCustomer(), true, $context);

                $success = true;
                $messages[] = ['type' => 'success', 'text' => $this->translator->trans('newsletter.subscriptionConfirmationSuccess')];
            } catch (\Exception $exception) {
                $success = false;
                $messages[] = ['type' => 'danger', 'text' => $this->translator->trans('newsletter.subscriptionConfirmationFailed')];
            }

            return $this->renderStorefront('@Storefront/page/account/newsletter.html.twig', [
                'customer' => $context->getCustomer(),
                'messages' => $messages,
                'success' => $success,
            ]);
        }

        try {
            $this->newsletterSubscriptionService->unsubscribe($this->hydrateFromCustomer($dataBag, $context->getCustomer()), $context);
            $this->accountService->setNewsletterFlag($context->getCustomer(), false, $context);

            $success = true;
            $messages[] = ['type' => 'success', 'text' => $this->translator->trans('newsletter.subscriptionRevokeSuccess')];
        } catch (\Exception $exception) {
            $success = false;
            $messages[] = ['type' => 'danger', 'text' => $this->translator->trans('error.message-default')];
        }

        return $this->renderStorefront('@Storefront/page/account/newsletter.html.twig', [
            'customer' => $context->getCustomer(),
            'messages' => $messages,
            'success' => $success,
        ]);
    }

    private function hydrateFromCustomer(RequestDataBag $dataBag, CustomerEntity $customer): RequestDataBag
    {
        $dataBag->set('email', $customer->getEmail());
        $dataBag->set('salutationId', $customer->getSalutationId());

        return $dataBag;
    }
}
