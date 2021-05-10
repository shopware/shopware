<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Captcha;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class BasicCaptchaPageletLoadedEvent extends PageletLoadedEvent
{
    protected BasicCaptchaPagelet $pagelet;

    public function __construct(
        BasicCaptchaPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->pagelet = $pagelet;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): BasicCaptchaPagelet
    {
        return $this->pagelet;
    }
}
