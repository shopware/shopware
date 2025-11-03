<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\App\Api\AppJWTGenerateRoute;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
#[Package('framework')]
final readonly class AppController
{
    public function __construct(private AppJWTGenerateRoute $appJWTGenerateRoute)
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
