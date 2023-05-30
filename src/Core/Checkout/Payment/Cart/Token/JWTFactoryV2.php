<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Checkout\Payment\Exception\TokenInvalidatedException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
class JWTFactoryV2 implements TokenFactoryInterfaceV2
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @internal
     */
    public function __construct(
        Configuration $configuration,
        private readonly Connection $connection
    ) {
        $this->configuration = $configuration;
    }

    public function generateToken(TokenStruct $tokenStruct): string
    {
        $expires = new \DateTimeImmutable('@' . time());

        // @see https://github.com/php/php-src/issues/9950
        if ($tokenStruct->getExpires() > 0) {
            $expires = $expires->modify(
                sprintf('+%d seconds', $tokenStruct->getExpires())
            );
        } else {
            $expires = $expires->modify(
                sprintf('-%d seconds', abs($tokenStruct->getExpires()))
            );
        }

        $jwtToken = $this->configuration->builder()
            ->identifiedBy(Uuid::randomHex())
            ->issuedAt(new \DateTimeImmutable('@' . time()))
            ->canOnlyBeUsedAfter(new \DateTimeImmutable('@' . time()))
            ->expiresAt($expires)
            ->relatedTo($tokenStruct->getTransactionId() ?? '')
            ->withClaim('pmi', $tokenStruct->getPaymentMethodId())
            ->withClaim('ful', $tokenStruct->getFinishUrl())
            ->withClaim('eul', $tokenStruct->getErrorUrl())
            ->getToken($this->configuration->signer(), $this->configuration->signingKey());

        $this->write($jwtToken->toString(), $expires);

        return $jwtToken->toString();
    }

    /**
     * @throws InvalidTokenException
     */
    public function parseToken(string $token): TokenStruct
    {
        try {
            /** @var UnencryptedToken $jwtToken */
            $jwtToken = $this->configuration->parser()->parse($token);
        } catch (\Throwable) {
            throw new InvalidTokenException($token);
        }

        if (!$this->configuration->validator()->validate($jwtToken, ...$this->configuration->validationConstraints())) {
            throw new InvalidTokenException($token);
        }

        if (!$this->has($token)) {
            throw new TokenInvalidatedException($token);
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
