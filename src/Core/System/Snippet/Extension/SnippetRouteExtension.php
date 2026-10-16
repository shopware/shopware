<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Snippet\SalesChannel\SnippetRouteResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SnippetRouteResponse>
 */
#[Package('discovery')]
final class SnippetRouteExtension extends Extension
{
    public const NAME = 'snippet-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
