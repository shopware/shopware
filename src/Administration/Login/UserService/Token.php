<?php declare(strict_types=1);

namespace Shopware\Administration\Login\UserService;

use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('after-sales')]
final class Token
{
    public function __construct(
        public readonly string $token,
        public readonly string $refreshToken
    ) {
    }

    public function toJson(): string
    {
        return \json_encode([
            'token' => $this->token,
            'refreshToken' => $this->refreshToken,
        ]);
    }

    public static function fromJson(string $json): self
    {
        $data = \json_decode($json, true);
        self::validate($data);

        return new self(
            $data['token'],
            $data['refreshToken']
        );
    }

    public static function fromArray(array $data): self
    {
        self::validate($data);

        return new self(
            $data['token'],
            $data['refreshToken']
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function validate(array $data): void
    {
        $violations = Validation::createValidator()->validate($data, self::createConstraints());
        if ($violations->count() === 0) {
            return;
        }

        $missingFields = [];
        foreach ($violations as $violation) {
            $missingFields[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        throw LoginException::invalidRefreshOrAccessToken($missingFields);
    }

    private static function createConstraints(): Collection
    {
        return new Collection([
            'token' => [
                new NotBlank(null, 'is required'),
                new Type('string', 'Needs to be a string'),
            ],
            'refreshToken' => [
                new NotBlank(null, 'is required'),
                new Type('string', 'Needs to be a string'),
            ],
        ]);
    }
}
