<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

use Shopware\Core\Framework\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StorefrontSeoUrlPlaceholderHandler implements SeoUrlPlaceholderHandlerInterface
{
    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $inner;

    public function __construct(SeoUrlPlaceholderHandlerInterface $inner)
    {
        $this->inner = $inner;
    }

    public function generate($name, $parameters = []): string
    {
        return $this->inner->generate($name, $parameters);
    }

    public function replacePlaceholder(Request $request, Response $response, ?string $host = null): void
    {
        $host = $host ?? $this->getHost($request);
        $this->inner->replacePlaceholder($request, $response, $host);
    }

    public function generateResolved(Request $request, $name, $parameters = [], ?string $host = null): string
    {
        $host = $host ?? $this->getHost($request);

        return $this->inner->generateResolved($request, $name, $parameters, $host);
    }

    private function getHost(Request $request): string
    {
        return $request->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL)
            . $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL);
    }
}
