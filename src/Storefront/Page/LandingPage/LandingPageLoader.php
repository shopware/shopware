<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\LandingPage;

use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\LandingPage\LandingPageException;
use Shopware\Core\Content\LandingPage\SalesChannel\AbstractLandingPageRoute;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRoute;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('discovery')]
class LandingPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly AbstractLandingPageRoute $landingPageRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?AbstractContentRoute $contentRoute = null,
    ) {
    }

    public function load(Request $request, SalesChannelContext $context): LandingPage
    {
        $landingPageId = $request->attributes->get('landingPageId');
        if (!$landingPageId) {
            throw RoutingException::missingRequestParameter('landingPageId', '/landingPageId');
        }

        $landingPage = $this->landingPageRoute->load($landingPageId, $request, $context)->getLandingPage();

        $contentPage = $this->loadContentPage($landingPageId, $request, $context);

        if ($landingPage->getCmsPage() === null && $contentPage === null) {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                throw new PageNotFoundException($landingPageId); // @phpstan-ignore shopware.domainException
            }

            throw LandingPageException::notFound($landingPageId);
        }

        $page = $this->genericPageLoader->load($request, $context);
        $page = LandingPage::createFrom($page);

        if ($contentPage !== null) {
            $page->addExtension('contentPage', $contentPage);
        }

        $page->setLandingPage($landingPage);
        $page->setNavigationId($landingPage->getId());

        $metaTitle = $landingPage->getTranslation('metaTitle') ?? $landingPage->getTranslation('name');
        $metaDescription = $landingPage->getTranslation('metaDescription');
        $metaKeywords = $landingPage->getTranslation('keywords');

        $metaInformation = new MetaInformation();
        $metaInformation->setMetaTitle((string) $metaTitle);
        $metaInformation->setMetaDescription((string) $metaDescription);
        $metaInformation->setMetaKeywords((string) $metaKeywords);
        $page->setMetaInformation($metaInformation);

        $this->eventDispatcher->dispatch(
            new LandingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadContentPage(string $landingPageId, Request $request, SalesChannelContext $context): ?ContentPage
    {
        if ($this->contentRoute === null) {
            return null;
        }

        $contentRequest = $request->duplicate();
        foreach (['includes', 'excludes', 'elementId'] as $parameter) {
            $contentRequest->attributes->remove($parameter);
            $contentRequest->query->remove($parameter);
            $contentRequest->request->remove($parameter);
        }

        try {
            $response = $this->contentRoute->load('/landing-page/' . $landingPageId, $contentRequest, $context);
        } catch (\Exception) {
            return null;
        } finally {
            // Content rendering can disable HTTP caching on the request it receives.
            if ($contentRequest->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE) === false) {
                $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, false);
            }
        }

        return $response instanceof ContentRouteResponse ? $response->getContentPage() : null;
    }
}
