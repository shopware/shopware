<?php declare(strict_types=1);

namespace Shopware\Administration\Login\TokenService;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Parser;
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
    ) {}

    public static function createFromIdToken(string $idToken): self
    {
        $parsedToken = self::parse($idToken);
        self::validate($parsedToken);

        return new self(
            $parsedToken->get('sub'),
            $parsedToken->get('email'),
            $parsedToken->get('exp'),
        );
    }

    private static function parse(string $idToken): DataSet
    {
        $parser = new Parser(new JoseEncoder());
        $parsed = $parser->parse($idToken);

        /**
         * Example JWT content:
         *
         * array: [
         *      "aud" => array: [
         *          0 => "ffffffff-ffff-ffff-ffff-ffffffffffff"
         *      ]
         *      "iss" => string: "https://api.shopware.com"
         *      "iat" => DateTimeImmutable: {
         *          date: 1970-01-01 00:00:00.0 +00:00
         *      }
         *      "exp" => DateTimeImmutable: {
         *          date: 1970-01-01 00:00:00.0 +00:00
         *      }
         *      "sub" => string: "123456"
         *      "email" => string: "test@shopware.com"
         * ]
         */
        return $parsed->claims();
    }

    private static function validate(DataSet $dataSet): void
    {
        $violations = Validation::createValidator()->validate($dataSet->all(), self::createConstraints());
        if ($violations->count() === 0) {
            return;
        }

        $missingFields = [];
        foreach ($violations as $violation) {
            $missingFields[] = $violation->getPropertyPath();
        }

        throw LoginException::idTokenNotValid($missingFields);
    }

    private static function createConstraints(): Collection
    {
        return new Collection([
            'aud' => new NotBlank(),
            'iss' => new NotBlank(),
            'iat' => new NotBlank(),
            'exp' => new NotBlank(),
            'sub' => new NotBlank(),
            'email' => [
                new NotBlank(),
                new Email(),
            ],
        ]);
    }
}
