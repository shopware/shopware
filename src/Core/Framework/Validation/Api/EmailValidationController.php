<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Validation\Email\EmailDto;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class EmailValidationController extends AbstractController
{
    #[Route(path: '/api/_action/validation/email', name: 'api.validation.email', defaults: ['auth_required' => false], methods: [Request::METHOD_POST], format: 'json')]
    public function validateEmailAddress(
        #[MapRequestPayload]
        EmailDto $email
    ): JsonResponse {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
