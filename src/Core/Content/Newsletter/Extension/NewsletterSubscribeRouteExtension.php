<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Extension;

use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NewsletterSubscribeRouteResponse>
 */
#[Package('after-sales')]
final class NewsletterSubscribeRouteExtension extends Extension
{
    public const NAME = 'newsletter-subscribe-route.subscribe';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $dataBag,
        public readonly SalesChannelContext $context,
        public readonly bool $validateStorefrontUrl,
    ) {
    }
}
