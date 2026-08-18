<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route exchanges a handoff token for the context token it refers to.
 * A handoff token can only be redeemed once.
 */
#[Package('framework')]
abstract class AbstractContextHandoffRedeemRoute
{
    abstract public function getDecorated(): AbstractContextHandoffRedeemRoute;

    abstract public function redeem(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse;
}
