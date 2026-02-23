<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

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
     * This method will be removed.
     *
     * @return StoreApiResponse<covariant Struct>
     */
    abstract public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse;

    /**
     * @deprecated tag:v6.8.0 - Will become abstract with SuccessResponse return type in v6.8.0.
     *
     * @return StoreApiResponse<covariant Struct>
     */
    public function confirmWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'Method "confirmWithResponse()" will be abstract in v6.8.0.0. Override it in %s, as the "confirm()" method will be removed.',
                static::class
            )
        );

        return $this->confirm($dataBag, $context);
    }
}
