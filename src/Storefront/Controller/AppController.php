<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\App\Api\AppJWTGenerateRoute;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
final class AppController
{
    public function __construct(private readonly AppJWTGenerateRoute $appJWTGenerateRoute)
    {
    }

    #[Route(path: '/app-system/{name}/generate-token', name: 'frontend.app-system.generate-token', defaults: ['_noStore' => true], methods: ['POST'])]
    public function generateToken(string $name, SalesChannelContext $context): Response
    {
        try {
            return $this->appJWTGenerateRoute->generate($name, $context);
        } catch (AppException $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }
}
