<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router as SymfonyRouter;
use Symfony\Component\Routing\RouterInterface;

class Router implements RouterInterface, RequestMatcherInterface
{
    /**
     * @var SymfonyRouter
     */
    private $decorated;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(SymfonyRouter $decorated, RequestStack $requestStack)
    {
        $this->decorated = $decorated;
        $this->requestStack = $requestStack;
    }

    public function matchRequest(Request $request)
    {
        return $this->decorated->matchRequest($request);
    }

    public function setContext(RequestContext $context)
    {
        return $this->decorated->setContext($context);
    }

    public function getContext()
    {
        return $this->decorated->getContext();
    }

    public function getRouteCollection()
    {
        return $this->decorated->getRouteCollection();
    }

    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH)
    {
        $generated = $this->decorated->generate($name, $parameters, $referenceType);

        if (!$this->isStorefrontRoute($name)) {
            return $generated;
        }

        $url = $this->getBaseUrl();

        switch ($referenceType) {
            case self::NETWORK_PATH:
            case self::ABSOLUTE_URL:
                $host = $this->getContext()->getHost();

                return str_replace(
                    $host,
                    $host . rtrim($url, '/'),
                    $generated
                );

            case self::RELATIVE_PATH:
                return ltrim($url, '/') . $generated;

            case self::ABSOLUTE_PATH:
            default:
                return rtrim($url, '/') . $generated;
        }
    }

    public function match($pathinfo)
    {
        return $this->decorated->match($pathinfo);
    }

    private function getBaseUrl(): string
    {
        $request = $this->requestStack->getMasterRequest();
        if (!$request) {
            return '';
        }

        $url = (string) $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL);

        if (empty($url)) {
            return $url;
        }

        return '/' . trim($url, '/') . '/';
    }

    private function isStorefrontRoute(string $name): bool
    {
        return strncmp($name, 'frontend.', 9) === 0 || strncmp($name, 'widgets.', 8) === 0;
    }
}
