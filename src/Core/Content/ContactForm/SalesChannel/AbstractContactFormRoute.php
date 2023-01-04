<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('content')]
abstract class AbstractContactFormRoute
{
    abstract public function getDecorated(): AbstractContactFormRoute;

    abstract public function load(RequestDataBag $data, SalesChannelContext $context): ContactFormRouteResponse;
}
