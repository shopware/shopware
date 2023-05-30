<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\RecoverPassword;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the AccountRecoverPasswordPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.13.0
 *
 * @final
 */
#[Package('customer-order')]
class AccountRecoverPasswordPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'account-recover-password-page-loaded';

    public function __construct(
        private readonly AccountRecoverPasswordPage $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
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
