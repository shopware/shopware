<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver\ResolverContext;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @package content
 */
class ResolverContext
{
    public function __construct(private readonly SalesChannelContext $context, private readonly Request $request)
    {
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
