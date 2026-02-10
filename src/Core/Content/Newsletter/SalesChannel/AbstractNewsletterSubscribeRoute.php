<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * This route is used to subscribe to the newsletter
 * The required parameters are: "email" and "option"
 * Valid "option" arguments: "subscribe" for double optin and "direct" to skip double optin
 * Optional parameters are: "salutationId", "firstName", "lastName", "street", "city" and "zipCode"
 */
#[Package('after-sales')]
abstract class AbstractNewsletterSubscribeRoute
{
    abstract public function getDecorated(): AbstractNewsletterSubscribeRoute;

    /**
     * @deprecated tag:v6.8.0
     * Use subscribeWithResponse() instead.
     * Starting with v6.8.0, the API route response is changing.
     * This method will be removed, and the route annotation will be moved to subscribeWithResponse().
     *
     * @return StoreApiResponse<covariant Struct>
     */
    abstract public function subscribe(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): StoreApiResponse;

    public function subscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): NewsletterSubscribeRouteResponse
    {
        return $this->getDecorated()->subscribeWithResponse($dataBag, $context, $validateStorefrontUrl);
    }
}
