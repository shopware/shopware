<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the AccountPaymentMethodPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 */
class AccountPaymentMethodPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'account-payment-method-page-loaded';

    private AccountPaymentMethodPage $page;

    public function __construct(AccountPaymentMethodPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): AccountPaymentMethodPage
    {
        return $this->page;
    }
}
