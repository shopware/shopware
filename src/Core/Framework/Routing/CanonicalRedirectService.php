<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class CanonicalRedirectService
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $configService)
    {
    }

    /**
     * getRedirect takes a request processed by the RequestTransformer and checks,
     * whether it points to a SEO-URL which has been superseded. In case the corresponding
     * configuration option is active, it returns a redirect response to indicate, that
     * the request should be redirected to the canonical URL.
     */
    public function getRedirect(Request $request): ?Response
    {
        // This attribute has been set by the RequestTransformer if the requested URL was superseded.
        $canonical = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK);
        $shouldRedirect = $this->configService->get('core.seo.redirectToCanonicalUrl');

        if (!$shouldRedirect) {
            return null;
        }

        if (!\is_string($canonical) || empty($canonical)) {
            return null;
        }

        $queryString = $request->getQueryString();

        if ($queryString) {
            $canonical = sprintf('%s?%s', $canonical, $queryString);
        }

        return new RedirectResponse($canonical, Response::HTTP_MOVED_PERMANENTLY);
    }
}
