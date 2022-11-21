<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Country;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the CountryStateDataPagelet is loaded
 *
 * @package storefront
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 */
class CountryStateDataPageletLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'country-sate-data-pagelet-loaded';

    private CountryStateDataPagelet $pagelet;

    public function __construct(CountryStateDataPagelet $pagelet, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->pagelet = $pagelet;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): CountryStateDataPagelet
    {
        return $this->pagelet;
    }
}
