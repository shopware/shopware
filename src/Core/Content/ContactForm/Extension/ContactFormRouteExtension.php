<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\Extension;

use Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContactFormRouteResponse>
 */
#[Package('discovery')]
final class ContactFormRouteExtension extends Extension
{
    public const NAME = 'contact-form-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $data,
        public readonly SalesChannelContext $context,
    ) {
    }
}
