<?php declare(strict_types=1);

namespace Shopware\Core\Framework\JWT\SalesChannel;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\JWT\Struct\JWTStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @template T of JWTStruct
 */
#[Package('checkout')]
abstract class JWTGenerator
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly DataValidator $validator,
    ) {
    }

    /**
     * @param T $jwt
     *
     * @return non-empty-string
     */
    public function encode(JWTStruct $jwt): string
    {
        return $this->buildToken($jwt)->getToken(
            $this->configuration->signer(),
            $this->configuration->signingKey()
        )->toString();
    }

    /**
     * @return T
     */
    public function decode(string $token, bool $disableValidation = false): JWTStruct
    {
        if (!$token) {
            throw JWTException::invalidJwt('JWT cannot be empty');
        }

        try {
            $jwt = $this->configuration->parser()->parse($token);
        } catch (\Exception $e) {
            throw JWTException::invalidJwt('Failed to parse JWT: ' . $e->getMessage(), $e);
        }
        if (!$jwt instanceof UnencryptedToken) {
            throw JWTException::invalidJwt('JWT is not an unencrypted token');
        }

        $structClass = $this->getJWTStructClass();
        $claims = $jwt->claims()->all();
        if ($disableValidation) {
            return new ($structClass)($claims);
        }

        if (!$this->configuration->validator()->validate($jwt, ...$this->getTokenValidationConstraints())) {
            throw JWTException::invalidJwt('JWT validation failed');
        }

        $this->validator->validate($claims, $this->getStructConstraints());

        return new ($structClass)($claims);
    }

    /**
     * @param T $jwt
     */
    protected function buildToken(JWTStruct $jwt): Builder
    {
        $now = new DatePoint();

        $jwt->iat ??= $now;
        $jwt->nbf ??= $now;
        $jwt->exp ??= $now->modify(\sprintf('+%d seconds', $this->getTokenLifetime($jwt)));
        $jwt->jti ??= Uuid::randomHex();

        $builder = $this->configuration->builder()
            ->issuedAt($jwt->iat)
            ->canOnlyBeUsedAfter($jwt->nbf)
            ->expiresAt($jwt->exp);

        foreach ($jwt->getVars() as $key => $value) {
            if (!$value && \in_array($key, RegisteredClaims::ALL, true) || \in_array($key, RegisteredClaims::DATE_CLAIMS, true)) {
                continue;
            }

            if ($key === RegisteredClaims::ID) {
                $builder = $builder->identifiedBy($value);
                continue;
            }

            if (!$key || !$value) {
                continue;
            }

            if ($key === RegisteredClaims::AUDIENCE) {
                $builder = $builder->permittedFor(...$value);
                continue;
            }

            if ($key === RegisteredClaims::SUBJECT) {
                $builder = $builder->relatedTo($value);
                continue;
            }

            if ($key === RegisteredClaims::ISSUER) {
                $builder = $builder->issuedBy($value);
                continue;
            }

            $builder = $builder->withClaim($key, $value);
        }

        return $builder;
    }

    /**
     * allows modifying Lcobucci validation constraints,
     * e.g. if tokens are signed in a certain way, e.g. JWKS (or not at all) or need to adhere to other format standards
     *
     * @return array<Constraint>
     */
    protected function getTokenValidationConstraints(): array
    {
        return $this->configuration->validationConstraints();
    }

    /**
     * lifetime in seconds (default is 1h)
     *
     * @param T $jwt
     */
    protected function getTokenLifetime(JWTStruct $jwt): int
    {
        return 3600;
    }

    /**
     * validates the payload of the decoded JWT
     * may be extended by subclass if they add additional claims to the payload or require a specific format
     */
    protected function getStructConstraints(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('jwt.' . $this->getJWTStructClass());
        $definition->add(RegisteredClaims::AUDIENCE, new Type('string'));
        $definition->add(RegisteredClaims::EXPIRATION_TIME, new NotBlank(), new NotNull(), new Type(\DateTimeImmutable::class));
        $definition->add(RegisteredClaims::ISSUER, new Type('string'));
        $definition->add(RegisteredClaims::ID, new Type('string'));
        $definition->add(RegisteredClaims::ISSUED_AT, new NotBlank(), new NotNull(), new Type(\DateTimeImmutable::class));
        $definition->add(RegisteredClaims::NOT_BEFORE, new NotBlank(), new NotNull(), new Type(\DateTimeImmutable::class));
        $definition->add(RegisteredClaims::SUBJECT, new Type('string'));

        return $definition;
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getJWTStructClass(): string;
}
