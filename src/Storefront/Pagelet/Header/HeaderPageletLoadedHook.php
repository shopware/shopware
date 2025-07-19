<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the HeaderPagelet is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.7.0.0
 *
 * @final
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class HeaderPageletLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'header-pagelet-loaded';

    public function __construct(
        private readonly HeaderPagelet $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): HeaderPagelet
    {
        return $this->page;
    }
}
