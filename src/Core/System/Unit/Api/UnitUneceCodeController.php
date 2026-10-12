<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Unit\UnitUneceCodes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class UnitUneceCodeController extends AbstractController
{
    public function __construct(private readonly UnitUneceCodes $uneceCodes)
    {
    }

    #[Route(path: '/api/_action/unit/unece-codes', name: 'api.action.unit.unece-codes', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['unit:read']], methods: ['GET'])]
    public function getUneceCodes(): JsonResponse
    {
        $codes = [];
        foreach ($this->uneceCodes->getCodes() as $code => $name) {
            $codes[] = ['code' => (string) $code, 'name' => $name];
        }

        return new JsonResponse($codes);
    }
}
