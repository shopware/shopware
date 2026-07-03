<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Events;

use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class CmsPageLoadedEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    protected CmsPageCollection $result;

    /**
     * @param CmsPageCollection $result
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'result', newType: 'CmsPageCollection')]
    public function __construct(
        protected Request $request,
        /* protected CmsPageCollection $result, */
        EntityCollection $result,
        protected SalesChannelContext $salesChannelContext,
    ) {
        $this->result = $result;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return CmsPageCollection
     */
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'CmsPageCollection')]
    public function getResult(): EntityCollection /* CmsPageCollection */
    {
        return $this->result;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
