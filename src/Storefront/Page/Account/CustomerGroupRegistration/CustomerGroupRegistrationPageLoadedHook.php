<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\CustomerGroupRegistration;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the CustomerGroupRegistrationPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 */
class CustomerGroupRegistrationPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'customer-group-registration-page-loaded';

    private CustomerGroupRegistrationPage $page;

    public function __construct(CustomerGroupRegistrationPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): CustomerGroupRegistrationPage
    {
        return $this->page;
    }
}
