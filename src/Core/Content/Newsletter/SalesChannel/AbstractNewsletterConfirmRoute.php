<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used to confirm the newsletter registration
 * The required parameters are: "hash" (received from the mail) and "email"
 */
#[Package('after-sales')]
abstract class AbstractNewsletterConfirmRoute
{
    abstract public function getDecorated(): AbstractNewsletterConfirmRoute;

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Return type will change to SuccessResponse. Use confirmWithResponse() instead.
     *
     * @return StoreApiResponse<covariant Struct>
     */
    abstract public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse;

    /**
     * Confirms the newsletter subscription and returns a typed response.
     * This method should be used instead of confirm() to get the typed response.
     */
    public function confirmWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): SuccessResponse
    {
        return $this->getDecorated()->confirmWithResponse($dataBag, $context);
    }
}
