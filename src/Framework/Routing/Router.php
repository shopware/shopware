<?php

namespace Shopware\Framework\Routing;

use Shopware\Defaults;
use Shopware\StorefrontApi\Context\StorefrontContextValueResolver;
use Shopware\StorefrontApi\Firewall\ApplicationAuthenticator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;

class Router extends \Symfony\Bundle\FrameworkBundle\Routing\Router
{
    private $container;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container, $resource, array $options = array(), RequestContext $context = null)
    {
        parent::__construct($container, $resource, $options, $context);

        $this->container = $container;
    }

    public function matchRequest(Request $request)
    {
        // todo: determine application by request
        $request->attributes->set(ApplicationAuthenticator::APPLICATION_ID, Defaults::SHOP);

        return $this->match($this->context->getPathInfo());
    }
}