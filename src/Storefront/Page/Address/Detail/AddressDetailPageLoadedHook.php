<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Detail;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the AddressDetailPage is loaded
 *
 * @hook-use-case data_loading
 */
class AddressDetailPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'address-detail-page-loaded';

    private AddressDetailPage $page;

    public function __construct(AddressDetailPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): AddressDetailPage
    {
        return $this->page;
    }
}
