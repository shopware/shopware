<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Offcanvas;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('core')]
class OffcanvasLoadedEvent
{
    public function __construct(
        public Offcanvas $page,
        public SalesChannelContext $context,
        public Request $request
    ) {
    }
}
