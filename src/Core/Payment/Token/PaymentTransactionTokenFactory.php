<?php declare(strict_types=1);

namespace Shopware\Payment\Token;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Shopware\Api\Order\Struct\OrderTransactionBasicStruct;
use Shopware\Context\Struct\ApplicationContext;
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

    public function generateToken(OrderTransactionBasicStruct $transaction, ApplicationContext $context): string
    {
        $expires = (new \DateTime())->modify('+30 minutes');

        $token = Random::getAlphanumericString(60);

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $tenantId = Uuid::fromHexToBytes($context->getTenantId());

        $this->connection->insert(
            'payment_token',
            [
                'id' => Uuid::uuid4()->getBytes(),
                'tenant_id' => $tenantId,
                'token' => $token,
                'payment_method_id' => Uuid::fromStringToBytes($transaction->getPaymentMethodId()),
                'payment_method_tenant_id' => $tenantId,
                'payment_method_version_id' => $versionId,
                'order_transaction_id' => Uuid::fromStringToBytes($transaction->getId()),
                'order_transaction_tenant_id' => $tenantId,
                'order_transaction_version_id' => $versionId,
                'expires' => $expires->format('Y-m-d H:i:s'),
            ]
        );

        return $token;
    }

    /**
     * @throws InvalidTokenException
     * @throws TokenExpiredException
     */
    public function validateToken(string $token, ApplicationContext $context): TokenStruct
    {
        $row = $this->connection->fetchAssoc(
            'SELECT * FROM payment_token WHERE token = :token AND tenant_id = :tenant',
            ['token' => $token, 'tenant' => Uuid::fromHexToBytes($context->getTenantId())]
        );

        if (!$row) {
            throw new InvalidTokenException($token);
        }

        $tokenStruct = new TokenStruct(
            $row['id'],
            $row['token'],
            Uuid::fromBytesToHex($row['payment_method_id']),
            Uuid::fromBytesToHex($row['order_transaction_id']),
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
    public function invalidateToken(string $token, ApplicationContext $context): bool
    {
        $affectedRows = $this->connection->delete(
            'payment_token',
            ['token' => $token, 'tenant_id' => Uuid::fromHexToBytes($context->getTenantId())]
        );

        if ($affectedRows !== 1) {
            throw new InvalidTokenException($token);
        }

        return true;
    }
}
