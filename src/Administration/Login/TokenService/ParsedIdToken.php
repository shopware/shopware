<?php declare(strict_types=1);

namespace Shopware\Administration\Login\TokenService;

use Lcobucci\JWT\Token\DataSet;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('after-sales')]
final class ParsedIdToken
{
    private function __construct(
        public readonly string $sub,
        public readonly string $email,
        public readonly \DateTimeInterface $expiry,
        public readonly string $username,
        public readonly string $givenName,
        public readonly string $familyName,
    ) {
    }

    public static function createFromDataSet(DataSet $dataSet): self
    {
        self::validate($dataSet);

        return new self(
            $dataSet->get('sub'),
            $dataSet->get('email'),
            $dataSet->get('exp'),
            $dataSet->get('preferred_username'),
            $dataSet->get('given_name'),
            $dataSet->get('family_name'),
        );
    }

    private static function validate(DataSet $dataSet): void
    {
        $violations = Validation::createValidator()->validate($dataSet->all(), self::createConstraints());
        if ($violations->count() === 0) {
            return;
        }

        $missingFields = [];
        foreach ($violations as $violation) {
            $missingFields[] = \sprintf('%s %s', $violation->getPropertyPath(), $violation->getMessage());
        }

        throw LoginException::invalidIdTokenDataSet($missingFields);
    }

    private static function createConstraints(): Collection
    {
        $constraints = new Collection([
            'exp' => new NotBlank(null, 'is empty'),
            'sub' => new NotBlank(null, 'is empty'),
            'preferred_username' => new NotBlank(null, 'is empty'),
            'given_name' => new NotBlank(null, 'is empty'),
            'family_name' => new NotBlank(null, 'is empty'),
            'email' => [
                new NotBlank(null, 'is empty'),
                new Email(null, 'is a invalid email address'),
            ],
        ]);

        $constraints->allowExtraFields = true;

        return $constraints;
    }
}
