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
     * @deprecated tag:v6.8.0
     * Use confirmWithResponse() instead.
     * Starting with v6.8.0, the API route response is changing.
     * This method will be removed and the route annotation will be moved to confirmWithResponse().
     *
     * @return StoreApiResponse<covariant Struct>
     */
    abstract public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse;

    public function confirmWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): SuccessResponse
    {
        return $this->getDecorated()->confirmWithResponse($dataBag, $context);
    }
}
