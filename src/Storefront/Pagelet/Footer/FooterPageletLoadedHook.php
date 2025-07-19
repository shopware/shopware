<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the FooterPagelet is loaded
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
class FooterPageletLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'footer-pagelet-loaded';

    public function __construct(
        private readonly FooterPagelet $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): FooterPagelet
    {
        return $this->page;
    }
}
