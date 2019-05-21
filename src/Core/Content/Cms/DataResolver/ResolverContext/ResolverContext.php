<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver\ResolverContext;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ResolverContext
{
    /**
     * @var SalesChannelContext
     */
    private $context;

    /**
     * @var Request
     */
    private $request;

    public function __construct(SalesChannelContext $context, Request $request)
    {
        $this->context = $context;
        $this->request = $request;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
