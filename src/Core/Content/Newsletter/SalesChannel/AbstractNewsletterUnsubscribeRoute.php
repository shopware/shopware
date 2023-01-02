<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used to unsubscribe the newsletter
 * The required parameters is "email"
 */
#[Package('customer-order')]
abstract class AbstractNewsletterUnsubscribeRoute
{
    abstract public function getDecorated(): AbstractNewsletterUnsubscribeRoute;

    abstract public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): NoContentResponse;
}
