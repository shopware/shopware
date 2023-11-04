<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used to subscribe to the newsletter
 * The required parameters are: "email" and "option"
 * Valid "option" arguments: "subscribe" for double optin and "direct" to skip double optin
 * Optional parameters are: "salutationId", "firstName", "lastName", "street", "city" and "zipCode"
 */
#[Package('customer-order')]
abstract class AbstractNewsletterSubscribeRoute
{
    abstract public function getDecorated(): AbstractNewsletterSubscribeRoute;

    abstract public function subscribe(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): NoContentResponse;
}
