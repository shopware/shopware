<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"sales-channel-api"})
 */
class SalesChannelNewsletterController extends AbstractController
{
    /**
     * @var AbstractNewsletterSubscribeRoute
     */
    private $newsletterSubscribeRoute;

    /**
     * @var AbstractNewsletterConfirmRoute
     */
    private $newsletterConfirmRoute;

    /**
     * @var AbstractNewsletterUnsubscribeRoute
     */
    private $newsletterUnsubscribeRoute;

    public function __construct(
        AbstractNewsletterSubscribeRoute $newsletterSubscribeRoute,
        AbstractNewsletterConfirmRoute $newsletterConfirmRoute,
        AbstractNewsletterUnsubscribeRoute $newsletterUnsubscribeRoute
    ) {
        $this->newsletterSubscribeRoute = $newsletterSubscribeRoute;
        $this->newsletterConfirmRoute = $newsletterConfirmRoute;
        $this->newsletterUnsubscribeRoute = $newsletterUnsubscribeRoute;
    }

    /**
     * @Route("/sales-channel-api/v{version}/newsletter/subscribe", name="sales-channel-api.newsletter.subscribe", methods={"POST"})
     */
    public function subscribe(Request $request, RequestDataBag $data, SalesChannelContext $context): JsonResponse
    {
        $data->set('storefrontUrl', $request->attributes->get('sw-sales-channel-absolute-base-url'));
        $data->set('option', 'subscribe');

        $this->newsletterSubscribeRoute->subscribe($data, $context, false);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/sales-channel-api/v{version}/newsletter/confirm", name="sales-channel-api.newsletter.confirm", methods={"POST"})
     */
    public function confirm(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $this->newsletterConfirmRoute->confirm($requestData, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/sales-channel-api/v{version}/newsletter/unsubscribe", name="sales-channel-api.newsletter.unsubscribe", methods={"POST"})
     */
    public function unsubscribe(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $requestData->set('option', 'unsubscribe');
        $this->newsletterUnsubscribeRoute->unsubscribe($requestData, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
