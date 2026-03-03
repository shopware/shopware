<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Api;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ValidationController extends AbstractController
{
    final public const EMAIL_KEY_WORD = 'email';
    final public const EMAILS_KEY_WORD = 'emails';

    /**
     * @internal
     */
    public function __construct(
        private readonly DataValidator $validator,
    ) {
    }

    #[Route(path: '/api/validation/email', name: 'api.validation.email', defaults: ['auth_required' => false], methods: [Request::METHOD_POST])]
    public function validateEmailAddress(Request $request): JsonResponse
    {
        $emailAddress = $request->request->get(self::EMAIL_KEY_WORD);
        if (!\is_string($emailAddress)) {
            // TODO: DOMAIN EXCEPTION
            throw ApiException::missingRequestParameter(self::EMAIL_KEY_WORD);
        }

        return new JsonResponse(['isValid' => $this->validateEmail($emailAddress)], Response::HTTP_OK);
    }

    #[Route(path: '/api/validation/emails', name: 'api.validation.emails', defaults: ['auth_required' => false], methods: ['POST'])]
    public function validateEmailAddresses(Request $request): JsonResponse
    {
        $emailAddresses = \json_decode((string) $request->request->get(self::EMAILS_KEY_WORD, ''), true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($emailAddresses)) {
            // TODO: DOMAIN EXCEPTION
            throw ApiException::missingRequestParameter(self::EMAILS_KEY_WORD);
        }

        $result = [];
        foreach ($emailAddresses as $emailAddress) {
            $result[] = [
                'email' => $emailAddress[self::EMAIL_KEY_WORD],
                'isValid' => $this->validateEmail($emailAddress[self::EMAIL_KEY_WORD]),
            ];
        }

        return new JsonResponse($result, Response::HTTP_OK);
    }

    private function validateEmail(?string $email): bool
    {
        $validationDefinition = new DataValidationDefinition(self::EMAILS_KEY_WORD);
        $validationDefinition->add(self::EMAIL_KEY_WORD, new NotBlank(), new Email());

        $violations = $this->validator->getViolations([self::EMAIL_KEY_WORD => $email], $validationDefinition);

        return $violations->count() === 0;
    }
}
