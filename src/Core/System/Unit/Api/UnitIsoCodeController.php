<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Unit\UnitIsoCodes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class UnitIsoCodeController extends AbstractController
{
    public function __construct(private readonly UnitIsoCodes $isoCodes)
    {
    }

    #[Route(path: '/api/_action/unit/iso-codes', name: 'api.action.unit.iso-codes', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['unit:read']], methods: ['GET'])]
    public function getIsoCodes(): JsonResponse
    {
        $codes = [];
        foreach ($this->isoCodes->getCodes() as $code => $name) {
            $codes[] = ['code' => (string) $code, 'name' => $name];
        }

        return new JsonResponse($codes);
    }
}
