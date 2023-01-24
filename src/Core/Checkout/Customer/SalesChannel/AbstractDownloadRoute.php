<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package customer-order
 */
abstract class AbstractDownloadRoute
{
    abstract public function getDecorated(): AbstractDownloadRoute;

    abstract public function load(Request $request, SalesChannelContext $context): Response;
}
