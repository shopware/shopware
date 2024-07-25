<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
class JWTFactoryV2 implements TokenFactoryInterfaceV2
{
    /**
     * @internal
     */
    public function __construct(
        private Configuration $configuration,
        private readonly Connection $connection
    ) {
    }

    public function generateToken(TokenStruct $tokenStruct): string
    {
        $expires = new \DateTimeImmutable('@' . time());

        // @see https://github.com/php/php-src/issues/9950
        if ($tokenStruct->getExpires() > 0) {
            $expires = $expires->modify(
                \sprintf('+%d seconds', $tokenStruct->getExpires())
            );
        } else {
            $expires = $expires->modify(
                \sprintf('-%d seconds', abs($tokenStruct->getExpires()))
            );
        }

        $jwtTokenBuilder = $this->configuration->builder()
            ->identifiedBy(Uuid::randomHex())
            ->issuedAt(new \DateTimeImmutable('@' . time()))
            ->canOnlyBeUsedAfter(new \DateTimeImmutable('@' . time()))
            ->expiresAt($expires)
            ->withClaim('pmi', $tokenStruct->getPaymentMethodId())
            ->withClaim('ful', $tokenStruct->getFinishUrl())
            ->withClaim('eul', $tokenStruct->getErrorUrl());

        $transactionId = $tokenStruct->getTransactionId();
        if ($transactionId !== '' && $transactionId !== null) {
            $jwtTokenBuilder = $jwtTokenBuilder->relatedTo($transactionId);
        }

        $token = $jwtTokenBuilder->getToken($this->configuration->signer(), $this->configuration->signingKey())->toString();
        $this->write(
            $token,
            $expires
        );

        return $token;
    }

    /**
     * @param non-empty-string $token
     */
    public function parseToken(string $token): TokenStruct
    {
        try {
            /** @var UnencryptedToken $jwtToken */
            $jwtToken = $this->configuration->parser()->parse($token);
        } catch (\Throwable $e) {
            throw PaymentException::invalidToken($token, $e);
        }

        // Remove LooseValidAt constraint, as we want to check it manually and throw a more specific exception
        $constraints = array_filter($this->configuration->validationConstraints(), fn ($constraint) => !$constraint instanceof LooseValidAt);

        if (!$this->configuration->validator()->validate($jwtToken, ...$constraints)) {
            throw PaymentException::invalidToken($token);
        }

        if (!$this->has($token)) {
            throw PaymentException::tokenInvalidated($token);
        }

        $errorUrl = $jwtToken->claims()->get('eul');

        /** @var \DateTimeImmutable $expires */
        $expires = $jwtToken->claims()->get('exp');

        return new TokenStruct(
            $jwtToken->claims()->get('jti'),
            $token,
            $jwtToken->claims()->get('pmi'),
            $jwtToken->claims()->get('sub'),
            $jwtToken->claims()->get('ful'),
            $expires->getTimestamp(),
            $errorUrl
        );
    }

    public function invalidateToken(string $token): bool
    {
        $this->delete($token);

        return false;
    }

    private function write(string $token, \DateTimeImmutable $expires): void
    {
        $this->connection->insert('payment_token', [
            'token' => self::normalize($token),
            'expires' => $expires->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function delete(string $token): void
    {
        $this->connection->executeStatement(
            'DELETE FROM payment_token WHERE token = :token',
            ['token' => self::normalize($token)]
        );
    }

    private function has(string $token): bool
    {
        $valid = $this->connection->fetchOne('SELECT token FROM payment_token WHERE token = :token', ['token' => self::normalize($token)]);

        return $valid !== false;
    }

    private static function normalize(string $token): string
    {
        return substr(hash('sha256', $token), 0, 32);
    }
}
