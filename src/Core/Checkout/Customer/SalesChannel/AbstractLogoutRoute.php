<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to logout the current context token
 */
abstract class AbstractLogoutRoute
{
    abstract public function getDecorated(): AbstractLogoutRoute;

    /**
     * @deprecated tag:v6.4.0 - Parameter $data will be mandatory in future implementation
     * @deprecated tag:v6.4.0 - Return typehint will be set to ContextTokenResponse in v6.4.0
     */
    abstract public function logout(SalesChannelContext $context/*, ?RequestDataBag $data = null*/);
}
