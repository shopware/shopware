<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
#[Package('framework')]
class AdminValidationController extends AbstractController
{
    final public const EMAIL_DEFINITION_NAME = 'email';

    /**
     * @internal
     */
    public function __construct(
        private readonly DataValidator $validator,
    ) {
    }

    #[Route(path: '/api/_action/validate/email', name: 'api.action.validate.email', defaults: ['auth_required' => false], methods: ['POST'])]
    public function validateEmailAddress(Request $request): JsonResponse
    {
        $emailAddress = $request->request->get(self::EMAIL_DEFINITION_NAME);
        if (!\is_string($emailAddress)) {
            throw ApiException::missingRequestParameter(self::EMAIL_DEFINITION_NAME);
        }

        $validationDefinition = new DataValidationDefinition(self::EMAIL_DEFINITION_NAME);
        $validationDefinition->add(self::EMAIL_DEFINITION_NAME, new Email());

        $violations = $this->validator->getViolations([self::EMAIL_DEFINITION_NAME => $emailAddress], $validationDefinition);

        if ($violations->count() > 0) {
            throw ApiException::invalidEmail($emailAddress);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
