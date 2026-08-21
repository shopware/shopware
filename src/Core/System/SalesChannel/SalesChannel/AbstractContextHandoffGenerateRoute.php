<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\ContextHandoffTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route mints a short lived, single use handoff token for the current context.
 * The token can be redeemed once via the redeem route to continue the same context in another client.
 */
#[Package('framework')]
abstract class AbstractContextHandoffGenerateRoute
{
    abstract public function getDecorated(): AbstractContextHandoffGenerateRoute;

    abstract public function generate(SalesChannelContext $context): ContextHandoffTokenResponse;
}
