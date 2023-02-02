<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the AccountProfilePage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 */
class AccountProfilePageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'account-profile-page-loaded';

    private AccountProfilePage $page;

    public function __construct(AccountProfilePage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): AccountProfilePage
    {
        return $this->page;
    }
}
