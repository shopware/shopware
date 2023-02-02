<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used to confirm the newsletter registration
 * The required parameters are: "hash" (received from the mail) and "email"
 */
#[Package('customer-order')]
abstract class AbstractNewsletterConfirmRoute
{
    abstract public function getDecorated(): AbstractNewsletterConfirmRoute;

    abstract public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): NoContentResponse;
}
