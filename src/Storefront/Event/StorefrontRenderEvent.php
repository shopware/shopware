<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class StorefrontRenderEvent extends NestedEvent
{
    /**
     * @var string
     */
    protected $view;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    public function __construct(string $view, array $parameters, Request $request, SalesChannelContext $context)
    {
        $this->view = $view;
        $this->parameters = array_merge(['context' => $context], $parameters);
        $this->request = $request;
        $this->context = $context;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getView(): string
    {
        return $this->view;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setParameter(string $key, $value): void
    {
        $this->parameters[$key] = $value;
    }
}
