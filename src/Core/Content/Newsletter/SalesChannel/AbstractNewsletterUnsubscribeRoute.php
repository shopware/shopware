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
     * @deprecated tag:v6.8.0 - reason:return-type-change - Return type will change to SuccessResponse. Use unsubscribeWithResponse() instead.
     *
     * @return StoreApiResponse<covariant Struct>
     */
    abstract public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse;

    /**
     * Unsubscribes from the newsletter and returns a typed response.
     * This method should be used instead of unsubscribe() to get the typed response.
     */
    public function unsubscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): SuccessResponse
    {
        return $this->getDecorated()->unsubscribeWithResponse($dataBag, $context);
    }
}
