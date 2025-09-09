<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Feature;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedHook;
use Shopware\Storefront\Page\Navigation\NavigationPageLoaderInterface;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoadedHook;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedHook;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoadedHook;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoaderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
#[Package('discovery')]
class NavigationController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly NavigationPageLoaderInterface $navigationPageLoader,
        private readonly MenuOffcanvasPageletLoaderInterface $offcanvasLoader,
        private readonly HeaderPageletLoaderInterface $headerLoader,
        private readonly FooterPageletLoaderInterface $footerLoader,
        private readonly AbstractCategoryUrlGenerator $categoryUrlGenerator,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
    ) {
    }

    #[Route(
        path: '/',
        name: 'frontend.home.page',
        options: ['seo' => true],
        defaults: ['_httpCache' => true],
        methods: ['GET'],
    )]
    public function home(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        $this->hook(new NavigationPageLoadedHook($page, $context));

        // TODO: Remove this once the new cms structure is fully implemented.
        if (Feature::isActive('STOREFRONT_COMPONENTS')) {
            $elements = $page->getCmsPage()->getAllElements();
            $listing = $elements[4];
            $listingData = $listing->getData()->getListing();

            $cmsPage = $this->getNewCmsStructure($listingData);

            return $this->renderStorefront(
                '@Storefront/storefront/page/content/index.html.twig',
                ['page' => $page, 'cmsPage' => $cmsPage]);
        }

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/navigation/{navigationId}',
        name: 'frontend.navigation.page',
        options: ['seo' => true],
        defaults: ['_httpCache' => true],
        methods: ['GET'],
    )]
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        $this->hook(new NavigationPageLoadedHook($page, $context));

        $category = $page->getCategory();
        \assert($category !== null);

        if ($category->getType() === CategoryDefinition::TYPE_LINK) {
            $host = $request->attributes->get(RequestTransformer::STOREFRONT_URL);
            $urlPlaceholder = $this->categoryUrlGenerator->generate($category, $context->getSalesChannel());

            if (!$urlPlaceholder) {
                throw CategoryException::categoryNotFound($category->getId());
            }

            return new RedirectResponse($this->seoUrlReplacer->replace($urlPlaceholder, $host, $context));
        }

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/widgets/menu/offcanvas',
        name: 'frontend.menu.offcanvas',
        defaults: ['XmlHttpRequest' => true, '_httpCache' => true],
        methods: ['GET'],
    )]
    public function offcanvas(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offcanvasLoader->load($request, $context);

        $this->hook(new MenuOffcanvasPageletLoadedHook($page, $context));

        $response = $this->renderStorefront(
            '@Storefront/storefront/layout/navigation/offcanvas/navigation-pagelet.html.twig',
            ['page' => $page]
        );

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    #[Route(
        path: '/_esi/global/header',
        name: 'frontend.header',
        defaults: ['XmlHttpRequest' => true, '_httpCache' => true, '_esi' => true],
        methods: ['GET'],
    )]
    public function header(Request $request, SalesChannelContext $context): Response
    {
        $header = $this->headerLoader->load($request, $context);

        $this->hook(new HeaderPageletLoadedHook($header, $context));

        return $this->renderStorefront('@Storefront/storefront/layout/header.html.twig', [
            'header' => $header,
            'headerParameters' => $request->get('headerParameters') ?? [],
        ]);
    }

    #[Route(
        path: '/_esi/global/footer',
        name: 'frontend.footer',
        defaults: ['XmlHttpRequest' => true, '_httpCache' => true, '_esi' => true],
        methods: ['GET'],
    )]
    public function footer(Request $request, SalesChannelContext $context): Response
    {
        $footer = $this->footerLoader->load($request, $context);

        $this->hook(new FooterPageletLoadedHook($footer, $context));

        return $this->renderStorefront('@Storefront/storefront/layout/footer.html.twig', [
            'footer' => $footer,
            'footerParameters' => $request->get('footerParameters') ?? [],
        ]);
    }

    /**
     * TODO: Remove after final CMS structure is implemented.
     */
    private static function getNewCmsStructure($listingData) {

        $media = new MediaEntity();
        $media->setId('123');
        $media->setMimeType('image/webp');
        $media->setFileExtension('webp');
        $media->setFileSize(1203165);
        $media->setFileName('inspire-connect-riseup-mood.webp');
        $media->setTitle('Test');
        $media->setAlt('Test');
        $media->setUrl('https://www.shopware.com/media/pages/products/shopping-experiences/inspire-connect-riseup-mood.webp');

        return [
            'id' => '123',
            'name' => 'Home',
            'elements' => [
                [
                    'id' => '123',
                    'component' => 'Sw:Grid:Container',
                    'properties' => [
                        'columns' => '2',
                        'columnsLg' => '1',
                        'gap' => '24',
                        'align' => 'start',
                        'alignContent' => 'start',
                        'justify' => 'stretch',
                        'justifyContent' => 'stretch',
                    ],
                    'slots' => [
                        'column-1' => [
                            [
                                'id' => '123',
                                'component' => 'Sw:Grid:Column',
                                'properties' => [
                                    'start' => null,
                                    'span' => null,
                                ],
                                'slots' => [
                                    'content' => [
                                        [
                                            'id' => '123',
                                            'component' => 'Sw:Media:Image',
                                            'properties' => [
                                                'media' => $media,
                                            ],
                                        ]
                                    ],
                                ]
                            ],
                        ],
                        'column-2' => [
                            [
                                'id' => 'ABC',
                                'component' => 'Sw:Grid:Column',
                                'properties' => [
                                    'start' => null,
                                    'span' => null,
                                ],
                                'slots' => [
                                    'default' => [
                                        [
                                            'id' => '123',
                                            'component' => 'Sw:Content:Text',
                                            'properties' => [
                                                'text' => '<h1>Lorem ipsum dolor sit amet.</h1><p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>',
                                            ],
                                        ],
                                        [
                                            'id' => '123',
                                            'component' => 'Sw:Alert',
                                            'properties' => [
                                                'text' => 'Hello World',
                                            ],
                                            'slots' => [
                                                'content' => [
                                                    [
                                                        'id' => '123',
                                                        'component' => 'Sw:Button',
                                                        'properties' => [
                                                            'text' => 'Click Me!',
                                                        ],
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ],
                ],
                [
                    'id' => '123',
                    'component' => 'SW:Product:Listing',
                    'properties' => [
                        'listing' => $listingData
                    ],
                ]
            ]
        ];
    }
}
