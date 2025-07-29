<?php declare(strict_types=1);

namespace Shopware\Administration\Login\TokenService;

use Shopware\Administration\Login\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
final readonly class TokenResult
{
    /**
     * @param non-empty-string $idToken
     * @param non-empty-string $accessToken
     * @param non-empty-string $refreshToken
     * @param non-empty-string $tokenType
     */
    private function __construct(
        public string $idToken,
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public string $tokenType,
    ) {
    }

    public static function createFromResponse(string $token): self
    {
        $response = json_decode($token, true);

        self::validateResponse($response);

        return new self(
            $response['id_token'],
            $response['access_token'],
            $response['refresh_token'],
            $response['expires_in'],
            $response['token_type'],
        );
    }

    /**
     * @param array<string, mixed> $response
     */
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
            'scope' => new NotBlank(),
        ]);
    }
}
