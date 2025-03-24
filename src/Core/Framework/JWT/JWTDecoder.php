<?php declare(strict_types=1);

namespace Shopware\Core\Framework\JWT;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\ConstraintViolation;
use Lcobucci\JWT\Validation\Validator;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
final class JWTDecoder
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function decode(string $jwt): array
    {
        return $this->parseToken($jwt)->claims()->all();
    }

    public function validate(string $jwt, Constraint ...$constraints): void
    {
        try {
            $validator = new Validator();
            $validator->assert($this->parseToken($jwt), ...$constraints);
        } catch (ConstraintViolation $e) {
            throw JWTException::invalidJwt($e->getMessage(), $e);
        }
    }

    private function parseToken(string $jwt): UnencryptedToken
    {
        if (!$jwt) {
            throw JWTException::invalidJwt('JWT cannot be empty');
        }

        try {
            $parser = new Parser(new JoseEncoder());
            $token = $parser->parse($jwt);
        } catch (\Exception $e) {
            throw JWTException::invalidJwt($e->getMessage(), $e);
        }

        if (!$token instanceof UnencryptedToken) {
            throw JWTException::invalidJwt('Incorrect token type');
        }

        return $token;
    }
}
