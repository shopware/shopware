<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

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
     * This method will be removed.
     *
     * @return StoreApiResponse<covariant Struct>
     */
    abstract public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse;

    /**
     * @deprecated tag:v6.8.0 - Will become abstract with SuccessResponse return type in v6.8.0.
     *
     * @return StoreApiResponse<covariant Struct>
     */
    public function unsubscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'Method "unsubscribeWithResponse()" will be abstract in v6.8.0.0. Override it in %s, as the "unsubscribe()" method will be removed.',
                static::class
            )
        );

        return $this->unsubscribe($dataBag, $context);
    }
}
