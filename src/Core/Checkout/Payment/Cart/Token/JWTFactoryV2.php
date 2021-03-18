<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Framework\Uuid\Uuid;

class JWTFactoryV2 implements TokenFactoryInterfaceV2
{
    /**
     * @var Configuration
     */
    protected $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function generateToken(TokenStruct $tokenStruct): string
    {
        $expires = (new \DateTimeImmutable('@' . time()))->modify(
            \sprintf('+%d seconds', $tokenStruct->getExpires())
        );

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
        } catch (\Throwable $e) {
            throw new InvalidTokenException($token);
        }

        if (!$this->configuration->validator()->validate($jwtToken, ...$this->configuration->validationConstraints())) {
            throw new InvalidTokenException($token);
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
        return false;
    }
}
