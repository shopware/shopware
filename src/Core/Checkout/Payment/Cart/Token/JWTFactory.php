<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use League\OAuth2\Server\CryptKey;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Framework\Uuid\Uuid;

class JWTFactory implements TokenFactoryInterface
{
    /**
     * @var CryptKey
     */
    protected $privateKey;

    /**
     * @param Key|CryptKey|string $privateKey
     */
    public function __construct($privateKey)
    {
        if (!$privateKey instanceof CryptKey) {
            $privateKey = new CryptKey($privateKey);
        }

        $this->privateKey = $privateKey;
    }

    public function generateToken(
        OrderTransactionEntity $transaction,
        ?string $finishUrl = null,
        int $expiresInSeconds = 1800
    ): string {
        $jwtToken = (new Builder())
            ->setId(Uuid::randomHex(), true)
            ->setIssuedAt(time())
            ->setNotBefore(time())
            ->setExpiration(time() + $expiresInSeconds)
            ->setSubject($transaction->getId())
            ->set('pmi', $transaction->getPaymentMethodId())
            ->set('ful', $finishUrl)
            ->sign(new Sha256(), new Key($this->privateKey->getKeyPath(), $this->privateKey->getPassPhrase()))
            ->getToken();

        return (string) $jwtToken;
    }

    /**
     * @throws InvalidTokenException
     */
    public function parseToken(string $token): TokenStruct
    {
        try {
            $jwtToken = (new Parser())->parse($token);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidTokenException($token);
        }

        if (!$jwtToken->verify(new Sha256(), $this->privateKey->getKeyPath())) {
            throw new InvalidTokenException($token);
        }

        $tokenStruct = new TokenStruct(
            $jwtToken->getClaim('jti'),
            $token,
            $jwtToken->getClaim('pmi'),
            $jwtToken->getClaim('sub'),
            $jwtToken->getClaim('ful'),
            $jwtToken->getClaim('exp')
        );

        return $tokenStruct;
    }

    public function invalidateToken(string $token): bool
    {
        return false;
    }
}
