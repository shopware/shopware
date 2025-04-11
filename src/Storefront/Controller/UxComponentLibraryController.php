<?php

declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Storefront\Framework\Twig\Components\UxComponentHelper;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
class UxComponentLibraryController extends StorefrontController
{
    public function __construct(
        private readonly UxComponentHelper $uxComponentHelper
    ) {}

    #[Route(path: '/ux-component-library', name: 'frontend.ux.component.library', defaults: ['_loginRequired' => false, '_noStore' => true], methods: ['GET'])]
    public function index(): Response
    {
        $components = $this->uxComponentHelper->getComponents();
        
        return $this->renderStorefront('@Storefront/storefront/page/ux-component-library/index.html.twig', [
            'components' => $components
        ]);
    }
}
