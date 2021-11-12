<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Suggest;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

class SuggestPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'suggest-page-loaded';

    private SuggestPage $page;

    public function __construct(SuggestPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): SuggestPage
    {
        return $this->page;
    }
}
