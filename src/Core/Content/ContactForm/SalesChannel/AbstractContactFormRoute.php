<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('content
This route can be used to send a contact form mail for the authenticated sales channel.
Required fields are: "salutationId", "firstName", "lastName", "email", "phone", "subject" and "comment"')]
abstract class AbstractContactFormRoute
{
    abstract public function getDecorated(): AbstractContactFormRoute;

    abstract public function load(RequestDataBag $data, SalesChannelContext $context): ContactFormRouteResponse;
}
