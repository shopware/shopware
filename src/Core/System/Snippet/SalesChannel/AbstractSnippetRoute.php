<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load the resolved snippets (translations) of the authenticated sales channel.
 * The language is taken from the `sw-language-id` header. Optionally the `prefixes` query parameter limits
 * the result to the given namespaces and the `languageIds` query parameter loads multiple languages at once.
 *
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 */
#[Package('discovery')]
abstract class AbstractSnippetRoute
{
    abstract public function load(Request $request, SalesChannelContext $context): SnippetRouteResponse;

    abstract public function getDecorated(): AbstractSnippetRoute;
}
