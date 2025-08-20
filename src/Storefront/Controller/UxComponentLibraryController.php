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
        $components = $this->uxComponentHelper->getComponents(true, true);

        dump($components);
        die();


        $componentsByNamespace = $this->buildNamespaceTreeFromComponents($components);

        return $this->renderStorefront('@Storefront/storefront/page/component-library/index.html.twig', [
            'components' => $components,
            'componentsByNamespace' => $componentsByNamespace
        ]);
    }

    private function buildNamespaceTreeFromComponents(iterable $components): array
    {
        $tree = [];
    
        foreach ($components as $comp) {
            $path = $comp->getName();
            $mainNamespace = $comp->getNamespace();
            $parts = explode(':', $path);

            if ($parts[0] !== $mainNamespace) {
                array_unshift($parts, $mainNamespace);
            }

            $componentKey = array_pop($parts);
            $rootNamespace = array_shift($parts);
    
            if (!isset($tree[$rootNamespace])) {
                $tree[$rootNamespace] = [
                    'name'     => ucwords($rootNamespace),
                    'components'    => [],
                    'subNamespaces' => [],
                ];
            }
    
            $cursor =& $tree[$rootNamespace];
            foreach ($parts as $ns) {
                if (!isset($cursor['subNamespaces'][$ns])) {
                    $cursor['subNamespaces'][$ns] = [
                        'name'     => ucwords($ns),
                        'components'    => [],
                        'subNamespaces' => [],
                    ];
                }
                $cursor =& $cursor['subNamespaces'][$ns];
            }
    
            $cursor['components'][$componentKey] = $comp;
        }
    
        return $tree;
    }
}
