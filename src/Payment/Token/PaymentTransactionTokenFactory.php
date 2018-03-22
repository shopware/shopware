<?php declare(strict_types=1);

namespace Shopware\Payment\Token;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Shopware\Api\Order\Struct\OrderTransactionBasicStruct;
use Shopware\Framework\Struct\Uuid;
use Shopware\Framework\Util\Random;
use Shopware\Payment\Exception\InvalidTokenException;
use Shopware\Payment\Exception\TokenExpiredException;

class PaymentTransactionTokenFactory implements PaymentTransactionTokenFactoryInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function generateToken(OrderTransactionBasicStruct $transaction): string
    {
        $expires = (new \DateTime())->modify('+30 minutes');

        $token = Random::getAlphanumericString(60);

        $this->connection->insert(
            'payment_token',
            [
                'id' => Uuid::uuid4()->getBytes(),
                'token' => $token,
                'payment_method_id' => Uuid::fromStringToBytes($transaction->getPaymentMethodId()),
                'transaction_id' => Uuid::fromStringToBytes($transaction->getId()),
                'expires' => $expires->format('Y-m-d H:i:s'),
            ]
        );

        return $token;
    }

    /**
     * @throws InvalidTokenException
     * @throws TokenExpiredException
     */
    public function validateToken(string $token): TokenStruct
    {
        $row = $this->connection->fetchAssoc('SELECT * FROM payment_token WHERE token = ?', [$token]);

        if (!$row) {
            throw new InvalidTokenException($token);
        }

        $tokenStruct = new TokenStruct(
            $row['id'],
            $row['token'],
            Uuid::fromBytesToHex($row['payment_method_id']),
            Uuid::fromBytesToHex($row['transaction_id']),
            new \DateTime($row['expires'])
        );

        if ($tokenStruct->isExpired()) {
            throw new TokenExpiredException($tokenStruct->getToken());
        }

        return $tokenStruct;
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
