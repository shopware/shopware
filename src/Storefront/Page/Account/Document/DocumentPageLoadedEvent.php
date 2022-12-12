<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Document;

use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @package customer-order
 *
 * @deprecated tag:v6.5.0 - Will removed, using DocumentRoute instead to load generated document blob
 */
class DocumentPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var DocumentPage
     */
    protected $page;

    public function __construct(DocumentPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): DocumentPage
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->page;
    }
}
