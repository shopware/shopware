<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso\TokenService;

use Lcobucci\JWT\Token\DataSet;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ParsedIdToken
{
    private function __construct(
        public string $sub,
        public string $email,
        public \DateTimeInterface $expiry,
        public string $username,
        public string $givenName,
        public string $familyName,
    ) {
    }

    public static function createFromDataSet(DataSet $dataSet): self
    {
        self::validate($dataSet);

        return new self(
            $dataSet->get('sub'),
            $dataSet->get('email'),
            $dataSet->get('exp'),
            self::prepareValue($dataSet->get('preferred_username'), $dataSet->get('email')),
            self::prepareValue($dataSet->get('given_name'), $dataSet->get('email')),
            self::prepareValue($dataSet->get('family_name'), $dataSet->get('email')),
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

        throw SsoException::invalidIdTokenDataSet($missingFields);
    }

    private static function createConstraints(): Collection
    {
        $constraints = new Collection([
            'exp' => new NotBlank(null, 'is empty'),
            'sub' => new NotBlank(null, 'is empty'),
            'email' => [
                new NotBlank(null, 'is empty'),
                new Email(null, 'is a invalid email address'),
            ],
        ]);

        $constraints->allowExtraFields = true;

        return $constraints;
    }

    /**
     * Initial we set the fields (userName, givenName, familyName) to email.
     * On first login with SSO we try to update all user data, but it is possible for old
     * datasets, that these fields are null or empty. Then we use the email again.
     */
    private static function prepareValue(?string $value, string $email): string
    {
        if ($value === null) {
            return $email;
        }

        if (trim($value) === '') {
            return $email;
        }

        return $value;
    }
}
