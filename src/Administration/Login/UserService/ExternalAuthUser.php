<?php declare(strict_types=1);

namespace Shopware\Administration\Login\UserService;

use League\OAuth2\Server\Entities\UserEntityInterface;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('after-sales')]
final class ExternalAuthUser implements UserEntityInterface
{
    /**
     * @param non-empty-string $userId
     */
    private function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $sub,
        public readonly Token $token,
        public readonly ?\DateTimeInterface $expiry,
        public readonly string $email,
        public readonly bool $isNew,
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->userId;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): self
    {
        self::validate($data);

        return new self(
            $data['id'],
            $data['user_id'],
            $data['user_sub'],
            Token::fromArray($data['token']),
            $data['expiry'],
            $data['email'],
            $data['is_new'],
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

        throw LoginException::loginUserInvalid($missingFields);
    }

    private static function createConstraints(): Collection
    {
        return new Collection([
            'id' => [
                new NotBlank(null, 'is required'),
                new Type('string'),
            ],
            'user_id' => [
                new NotBlank(null, 'is required'),
                new Type('string', 'Needs to be a string'),
            ],
            'user_sub' => [
                new NotBlank(null, 'is required'),
                new Type('string', 'Needs to be a string'),
            ],
            'token' => [
                new Type('array', 'Needs to be an array'),
                new Collection([
                    'token' => [
                        new NotBlank(null, 'is required'),
                        new Type('string', 'Needs to be a string'),
                    ],
                    'refreshToken' => [
                        new NotBlank(null, 'is required'),
                        new Type('string', 'Needs to be a string'),
                    ],
                ]),
            ],
            'expiry' => [
                new Type('DateTimeInterface', 'Needs to be a DateTimeInterface'),
            ],
            'email' => [
                new NotBlank(null, 'is required'),
                new Email(null, 'Needs to be a valid email address'),
            ],
            'is_new' => [
                new Type('bool', 'Needs to be a boolean'),
            ],
        ]);
    }
}
