<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Content\Newsletter\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"sales-channel-api"})
 */
class SalesChannelNewsletterController extends AbstractController
{
    /**
     * @var NewsletterSubscriptionServiceInterface
     */
    private $newsletterSubscriptionService;

    public function __construct(
        NewsletterSubscriptionServiceInterface $newsletterSubscriptionService
    ) {
        $this->newsletterSubscriptionService = $newsletterSubscriptionService;
    }

    /**
     * @Route("/sales-channel-api/v{version}/newsletter", name="sales-channel-api.newsletter.update", methods={"PATCH"})
     */
    public function update(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $this->newsletterSubscriptionService->update($requestData, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/sales-channel-api/v{version}/newsletter/subscribe", name="sales-channel-api.newsletter.subscribe", methods={"POST"})
     */
    public function subscribe(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $requestData->set('option', 'subscribe');
        $this->newsletterSubscriptionService->subscribe($requestData, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/sales-channel-api/v{version}/newsletter/confirm", name="sales-channel-api.newsletter.confirm", methods={"POST"})
     */
    public function confirm(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $this->newsletterSubscriptionService->confirm($requestData, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/sales-channel-api/v{version}/newsletter/unsubscribe", name="sales-channel-api.newsletter.unsubscribe", methods={"POST"})
     */
    public function unsubscribe(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $requestData->set('option', 'unsubscribe');
        $this->newsletterSubscriptionService->unsubscribe($requestData, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
