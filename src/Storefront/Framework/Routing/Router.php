<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

class Router implements RouterInterface, RequestMatcherInterface
{
    /**
     * @var \Symfony\Component\Routing\Router
     */
    private $decorated;

    /**
     * @var string
     */
    private $baseUrl;

    public function __construct(\Symfony\Component\Routing\Router $decorated, RequestStack $requestStack)
    {
        $this->decorated = $decorated;

        $this->baseUrl = $requestStack
            ->getMasterRequest()
            ->attributes
            ->get(RequestTransformer::SALES_CHANNEL_BASE_URL);
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

        if (strncmp($name, 'frontend.', 9) === 0 || strncmp($name, 'widgets.', 8) === 0) {
            return $this->baseUrl . $generated;
        }

        return $generated;
    }

    public function match($pathinfo)
    {
        return $this->decorated->match($pathinfo);
    }
}
