<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Newsletter\Register;

use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @package customer-order
 *
 * @deprecated tag:v6.5.0 - Will be removed
 */
class NewsletterRegisterPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var NewsletterRegisterPage
     */
    protected $page;

    public function __construct(NewsletterRegisterPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): NewsletterRegisterPage
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->page;
    }
}
