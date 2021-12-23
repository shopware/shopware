<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Maintenance;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the MaintenancePage is loaded
 *
 * @hook-use-case data_loading
 *
 * @internal (flag:FEATURE_NEXT_17441)
 */
class MaintenancePageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'maintenance-page-loaded';

    private MaintenancePage $page;

    public function __construct(MaintenancePage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): MaintenancePage
    {
        return $this->page;
    }
}
