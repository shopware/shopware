<?php declare(strict_types=1);

namespace Shopware\Administration\Login\TokenService;

use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('after-sales')]
final class TokenResult
{
    private function __construct(
        public readonly string $idToken,
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
        public readonly string $tokenType,
    ) {}

    public static function createFromResponse(string $token): self
    {
        $response = json_decode($token, true);

        self::validateResponse($response);

        // TODO: Validate id_token

        return new self(
            $response['id_token'],
            $response['access_token'],
            $response['refresh_token'],
            $response['expires_in'],
            $response['token_type'],
        );
    }

    private static function validateResponse(array $response): void
    {
        $violations = Validation::createValidator()->validate($response, self::createConstraints());
        if ($violations->count() === 0) {
            return;
        }

        $missingFields = [];
        foreach ($violations as $violation) {
            $missingFields[] = $violation->getPropertyPath();
        }

        throw LoginException::tokenNotValid($missingFields);
    }

    private static function createConstraints(): Collection
    {
        return new Collection([
            'id_token' => new NotBlank(),
            'access_token' => new NotBlank(),
            'refresh_token' => new NotBlank(),
            'expires_in' => new NotBlank(),
            'token_type' => new NotBlank(),
        ]);
    }
}
