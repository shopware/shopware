<?php declare(strict_types=1);

namespace Shopware\Administration\Login\UserService;

use League\OAuth2\Server\Entities\UserEntityInterface;
use Shopware\Administration\Login\LoginException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ExternalAuthUser implements UserEntityInterface
{
    /**
     * @param non-empty-string $userId
     */
    private function __construct(
        public string $id,
        public string $userId,
        public string $sub,
        public ?Token $token,
        public \DateTimeInterface $expiry,
        public string $email,
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
            \array_key_exists('token', $data) && \is_array($data['token']) ? Token::fromArray($data['token']) : null,
            $data['expiry'],
            $data['email'],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createFromDatabaseQuery(array $data): self
    {
        $data['id'] = Uuid::fromBytesToHex($data['id']);
        $data['user_id'] = Uuid::fromBytesToHex($data['user_id']);
        $data['expiry'] = new \DateTimeImmutable($data['expiry']);
        if (\array_key_exists('token', $data) && \is_string($data['token'])) {
            $data['token'] = json_decode($data['token'], true);
        }

        return self::create($data);
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
            'token' => new Optional([
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
            ]),
            'expiry' => [
                new Type('DateTimeInterface', 'Needs to be a DateTimeInterface'),
            ],
            'email' => [
                new NotBlank(null, 'is required'),
                new Email(null, 'Needs to be a valid email address'),
            ],
        ]);
    }
}
