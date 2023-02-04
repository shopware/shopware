<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\LandingPage;

use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\LandingPage\SalesChannel\AbstractLandingPageRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
class LandingPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly AbstractLandingPageRoute $landingPageRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws PageNotFoundException
     */
    public function load(Request $request, SalesChannelContext $context): LandingPage
    {
        $landingPageId = $request->attributes->get('landingPageId');
        if (!$landingPageId) {
            throw new MissingRequestParameterException('landingPageId', '/landingPageId');
        }

        $landingPage = $this->landingPageRoute->load($landingPageId, $request, $context)->getLandingPage();

        if ($landingPage->getCmsPage() === null) {
            throw new PageNotFoundException($landingPageId);
        }

        $page = $this->genericPageLoader->load($request, $context);
        $page = LandingPage::createFrom($page);

        $page->setLandingPage($landingPage);

        $metaInformation = new MetaInformation();
        $metaTitle = $landingPage->getMetaTitle() ?? $landingPage->getName();
        $metaInformation->setMetaTitle($metaTitle ?? '');
        $metaInformation->setMetaDescription($landingPage->getMetaDescription() ?? '');
        $metaInformation->setMetaKeywords($landingPage->getKeywords() ?? '');
        $page->setMetaInformation($metaInformation);

        $this->eventDispatcher->dispatch(
            new LandingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
