<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used to unsubscribe the newsletter
 * The required parameters is "email"
 */
#[Package('after-sales')]
abstract class AbstractNewsletterUnsubscribeRoute
{
    abstract public function getDecorated(): AbstractNewsletterUnsubscribeRoute;

    /**
     * @deprecated tag:v6.8.0
     * Use unsubscribeWithResponse() instead.
     * Starting with v6.8.0, the API route response is changing.
     * This method will be removed and the route annotation will be moved to unsubscribeWithResponse().
     *
     * @return StoreApiResponse<covariant Struct>
     */
    abstract public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse;

    public function unsubscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): SuccessResponse
    {
        return $this->getDecorated()->unsubscribeWithResponse($dataBag, $context);
    }
}
