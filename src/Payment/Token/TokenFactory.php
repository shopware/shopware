<?php declare(strict_types=1);

namespace Shopware\Payment\Token;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Shopware\Framework\Util\Random;
use Shopware\Payment\Exception\InvalidTokenException;
use Shopware\Payment\Exception\TokenExpiredException;

class TokenFactory implements TokenFactoryInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function generateToken(
        string $paymentMethodId,
        string $transactionId,
        \DateTime $expires = null,
        int $length = 60): string
    {
        if (!$expires) {
            $expires = (new \DateTime())->modify('+30 minutes');
        }

        $token = Random::getAlphanumericString($length);

        $this->connection->insert(
            'payment_token',
            [
                'id' => Uuid::uuid4()->getBytes(),
                'token' => $token,
                'payment_method_id' => Uuid::fromString($paymentMethodId)->getBytes(),
                'transaction_id' => Uuid::fromString($transactionId)->getBytes(),
                'expires' => $expires->format('Y-m-d H:i:s'),
            ]
        );

        return $token;
    }

    /**
     * @throws InvalidTokenException
     * @throws TokenExpiredException
     */
    public function validateToken(string $tokenIdentifier): TokenStruct
    {
        $row = $this->connection->fetchAssoc('SELECT * FROM payment_token WHERE token = ?', [$tokenIdentifier]);

        if (!$row) {
            throw new InvalidTokenException($tokenIdentifier);
        }

        $token = new TokenStruct(
            $row['id'],
            $row['token'],
            Uuid::fromBytes($row['payment_method_id'])->toString(),
            Uuid::fromBytes($row['transaction_id'])->toString(),
            new \DateTime($row['expires'])
        );

        if ($token->isExpired()) {
            throw new TokenExpiredException($token->getToken());
        }

        return $token;
    }

    /**
     * @throws InvalidTokenException
     * @throws InvalidArgumentException
     */
    public function invalidateToken(string $token): bool
    {
        $affectedRows = $this->connection->delete('payment_token', ['token' => $token]);

        if ($affectedRows !== 1) {
            throw new InvalidTokenException($token);
        }

        return true;
    }
}
