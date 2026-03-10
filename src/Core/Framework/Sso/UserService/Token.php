<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso\UserService;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
final readonly class Token implements \JsonSerializable
{
    private function __construct(
        public string $token,
        public string $refreshToken
    ) {
    }

    public static function fromJson(string $json): self
    {
        $data = \json_decode($json, true);

        return self::fromArray($data);
    }

    /**
     * @param array<string, string> $data
     */
    public static function fromArray(array $data): self
    {
        self::validate($data);

        return new self(
            $data['token'],
            $data['refreshToken']
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'token' => $this->token,
            'refreshToken' => $this->refreshToken,
        ];
    }

    /**
     * @param array<string, string> $data
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

        throw SsoException::invalidRefreshOrAccessToken($missingFields);
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
