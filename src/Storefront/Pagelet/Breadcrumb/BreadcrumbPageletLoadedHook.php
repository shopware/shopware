<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Breadcrumb;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the BreadcrumbPagelet is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.8.0.0
 *
 * @final
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class BreadcrumbPageletLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'breadcrumb-pagelet-loaded';

    public function __construct(
        private readonly BreadcrumbPagelet $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): BreadcrumbPagelet
    {
        return $this->page;
    }
}
