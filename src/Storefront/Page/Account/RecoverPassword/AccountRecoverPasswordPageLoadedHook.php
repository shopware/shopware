<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\RecoverPassword;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the AccountRecoverPasswordPage is loaded
 *
 * @package customer-order
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.13.0
 */
class AccountRecoverPasswordPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'account-recover-password-page-loaded';

    private AccountRecoverPasswordPage $page;

    public function __construct(AccountRecoverPasswordPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): AccountRecoverPasswordPage
    {
        return $this->page;
    }
}
