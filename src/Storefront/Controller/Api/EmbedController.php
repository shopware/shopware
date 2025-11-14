<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller\Api;

use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Seo\AbstractSeoResolver;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
#[Package('framework')]
class EmbedController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(
        private readonly AbstractProductDetailRoute $productDetailRoute,
        private readonly Environment $twig,
        private readonly AbstractSalesChannelContextFactory $contextFactory,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly DatabaseSalesChannelThemeLoader $themeLoader,
        private readonly ThemeRuntimeConfigStorage $themeRuntimeConfigStorage,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly MediaUrlPlaceholderHandlerInterface $mediaUrlPlaceholderHandler,
        private readonly AbstractSeoResolver $seoResolver,
    ) {
    }

    #[Route(path: '/oembed', name: 'oembed', defaults: ['auth_required' => false], methods: ['GET'])]
    public function oembed(Request $request): Response
    {
        $url = $request->query->get('url');

        if (!$url || !\is_string($url)) {
            throw new BadRequestHttpException('URL is required');
        }

        $parsedUrl = $this->parseAndValidateUrl($url);
        $baseUrl = $this->buildBaseUrl($parsedUrl);
        $matchedDomain = $this->findMatchingSalesChannelDomain($url);
        $pathInfo = $this->extractPathInfoFromUrl($url, $matchedDomain->getUrl());
        $productId = $this->resolveProductIdFromSeoUrl($pathInfo, $matchedDomain);
        
        $salesChannelContext = $this->contextFactory->create('', $matchedDomain->getSalesChannelId(), [
            'languageId' => $matchedDomain->getLanguageId(),
        ]);

        $product = $this->loadProductForEmbed(
            $productId, 
            $request, 
            $salesChannelContext
        );

        return $this->buildOembedResponse(
            $product, 
            $productId, 
            $matchedDomain->getSalesChannelId(), 
            $baseUrl, 
            $salesChannelContext
        );
    }

    #[Route(path: '/embed/product', name: 'embed.product', defaults: ['auth_required' => false], methods: ['GET'])]
    public function embedProduct(Request $request): Response
    {
        $productId = $request->query->get('productId');

        if (!$productId || !\is_string($productId)) {
            throw new BadRequestHttpException('Product ID is required');
        }

        // Get sales channel ID from query or use first available
        $salesChannelId = $request->query->get('salesChannelId');
        if (!$salesChannelId || !\is_string($salesChannelId)) {
            $salesChannel = $this->getFirstAvailableSalesChannel();
            $salesChannelId = $salesChannel->getId();
        }

        // Build SalesChannelContext
        $context = $this->contextFactory->create('', $salesChannelId);

        // Add context and theme to Twig globals
        $this->twig->addGlobal('context', $context);

        $themeId = $this->getThemeIdFromSalesChannel($salesChannelId);
        $this->twig->addGlobal('themeId', $themeId);

        // Set theme ID in request attributes for ThemeAssetPackage
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, $themeId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);

        // Load product using Store API route
        $criteria = new Criteria();
        $criteria->addAssociation('cover');
        $criteria->addAssociation('media');
        $criteria->addAssociation('manufacturer');

        try {
            $result = $this->productDetailRoute->load($productId, $request, $context, $criteria);
            $product = $result->getProduct();
        } catch (ProductNotFoundException $e) {
            throw $this->createNotFoundException('Product not found', $e);
        }

        $rendered = $this->twig->render('@Storefront/storefront/embeddable/product.html.twig', [
            'product' => $product,
            'context' => $context,
            'isEmbed' => true,
        ]);

        // Replace media and SEO URL placeholders, similar to StorefrontController
        $content = $this->mediaUrlPlaceholderHandler->replace($rendered);

        // Get the host URL from the sales channel's first domain
        $salesChannel = $context->getSalesChannel();
        $domains = $salesChannel->getDomains();
        $host = $domains !== null && $domains->count() > 0
            ? rtrim($domains->first()?->getUrl() ?? '', '/')
            : '';

        $content = $this->seoUrlPlaceholderHandler->replace($content, $host, $context);

        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/html');

        // Allow iframe embedding by removing X-Frame-Options
        $response->headers->remove('X-Frame-Options');

        // Set CSP to allow embedding from any domain
        $response->headers->set('Content-Security-Policy', "frame-ancestors *");

        // CORS headers for cross-origin embedding
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET');

        return $response;
    }

    private function getFirstAvailableSalesChannel(): SalesChannelEntity
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();

        if (!$salesChannel instanceof SalesChannelEntity) {
            throw SalesChannelException::salesChannelNotFound('');
        }

        return $salesChannel;
    }

    private function getThemeIdFromSalesChannel(string $salesChannelId): ?string
    {
        $themes = $this->themeLoader->load($salesChannelId);

        // The loader returns theme names, but we need the theme ID.
        // Get the theme ID from the database using the theme name.
        if (!empty($themes)) {
            $themeName = $themes[0];

            return $this->themeRuntimeConfigStorage->getThemeIdByTechnicalName($themeName);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAndValidateUrl(string $url): array
    {
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['scheme'], $parsedUrl['host'], $parsedUrl['path'])) {
            throw new BadRequestHttpException('Invalid URL format');
        }

        return $parsedUrl;
    }

    /**
     * @param array<string, mixed> $parsedUrl
     */
    private function buildBaseUrl(array $parsedUrl): string
    {
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        
        if (isset($parsedUrl['port'])) {
            $isNonStandardPort = ($parsedUrl['scheme'] === 'http' && $parsedUrl['port'] !== 80) 
                || ($parsedUrl['scheme'] === 'https' && $parsedUrl['port'] !== 443);
            
            if ($isNonStandardPort) {
                $baseUrl .= ':' . $parsedUrl['port'];
            }
        }

        return $baseUrl;
    }

    private function findMatchingSalesChannelDomain(string $url): SalesChannelDomainEntity
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('language');
        
        $domains = $this->salesChannelDomainRepository->search($criteria, $context)->getEntities();
        
        foreach ($domains as $domain) {
            $domainUrl = rtrim($domain->getUrl(), '/');
            if (str_starts_with($url, $domainUrl)) {
                return $domain;
            }
        }

        throw new NotFoundHttpException('No sales channel found for this URL');
    }

    private function extractPathInfoFromUrl(string $url, string $domainUrl): string
    {
        $domainUrl = rtrim($domainUrl, '/');
        $pathInfo = substr($url, \strlen($domainUrl));
        
        return '/' . ltrim($pathInfo, '/');
    }

    private function resolveProductIdFromSeoUrl(string $pathInfo, SalesChannelDomainEntity $domain): string
    {
        $resolved = $this->seoResolver->resolve(
            $domain->getLanguageId(),
            $domain->getSalesChannelId(),
            $pathInfo
        );

        // Parse the pathInfo to extract product ID
        // Expected format: /detail/{productId}
        if (!preg_match('#^/detail/([a-f0-9]{32})$#', $resolved['pathInfo'], $matches)) {
            throw new NotFoundHttpException('URL does not point to a valid product');
        }

        return $matches[1];
    }

    private function loadProductForEmbed(string $productId, Request $request, SalesChannelContext $context): ProductEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('cover');
        $criteria->addAssociation('manufacturer');

        try {
            $result = $this->productDetailRoute->load($productId, $request, $context, $criteria);
            
            return $result->getProduct();
        } catch (ProductNotFoundException $e) {
            throw new NotFoundHttpException('Product not found', $e);
        }
    }

    private function buildOembedResponse(
        ProductEntity $product,
        string $productId,
        string $salesChannelId,
        string $baseUrl,
        SalesChannelContext $context
    ): JsonResponse {
        $embedUrl = $baseUrl . '/embed/product?productId=' . $productId . '&salesChannelId=' . $salesChannelId;

        $embedCode = '<iframe src="' . $embedUrl . '" loading="lazy" '
            . 'sandbox="allow-scripts allow-same-origin allow-popups allow-top-navigation-by-user-activation" '
            . 'style="border: none;max-width: 100%;" width="320" height="600"></iframe>';

        $response = new JsonResponse([
            'version' => '1.0',
            'type' => 'rich',
            'provider_name' => $context->getSalesChannel()->getName(),
            'provider_url' => $baseUrl,
            'title' => $product->getTranslated()['name'] ?? $product->getName(),
            'html' => $embedCode,
            'width' => 320,
            'height' => 600,
        ]);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET');

        return $response;
    }
}