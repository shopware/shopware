<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Api;

use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class CompanyAccountNameFieldsController
{
    /**
     * @internal
     */
    public function __construct(private readonly CompanyAccountNameFields $companyAccountNameFields)
    {
    }

    #[Route(
        path: '/api/_action/customer/company-account-name-fields',
        name: 'api.action.customer.company-account-name-fields',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:update']],
        methods: ['GET']
    )]
    public function contactPersonRequired(Request $request): JsonResponse
    {
        $salesChannelId = $request->query->getString('salesChannelId');

        return new JsonResponse([
            'contactPersonRequired' => $this->companyAccountNameFields->areRequired($salesChannelId !== '' ? $salesChannelId : null),
        ]);
    }
}
