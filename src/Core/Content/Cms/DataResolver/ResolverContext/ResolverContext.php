<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver\ResolverContext;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
class ResolverContext
{
    public function __construct(
        private readonly SalesChannelContext $context,
        private readonly Request $request
    ) {
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
