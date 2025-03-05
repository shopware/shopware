<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class GenericPageLoader implements GenericPageLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(Request $request, SalesChannelContext $context): Page
    {
        return Profiler::trace('generic-page-loader', function () use ($request, $context) {
            $page = new Page();

            $page->setMetaInformation((new MetaInformation())->assign([
                'revisit' => '15 days',
                'robots' => 'index,follow',
                'xmlLang' => $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE) ?? '',
                'metaTitle' => $this->systemConfigService->getString('core.basicInformation.shopName', $context->getSalesChannelId()),
            ]));

            $this->eventDispatcher->dispatch(
                new GenericPageLoadedEvent($page, $context, $request)
            );

            return $page;
        });
    }
}
