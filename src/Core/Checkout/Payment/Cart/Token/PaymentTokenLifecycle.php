<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PaymentTokenLifecycle
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function invalidateToken(string $tokenId): bool
    {
        if (Feature::isActive('REPEATED_PAYMENT_FINALIZE')) {
            $this->connection->update('payment_token', ['consumed' => 1], ['token' => $tokenId]);
        } else {
            $this->connection->delete('payment_token', ['token' => $tokenId]);
        }

        return false;
    }

    public function addToken(string $tokenId, \DateTimeImmutable $expires): void
    {
        $this->connection->insert('payment_token', [
            'token' => $tokenId,
            'expires' => $expires->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function isRegistered(string $tokenId): bool
    {
        $token = $this->connection->fetchOne(
            'SELECT 1 FROM payment_token WHERE token = :token',
            ['token' => $tokenId]
        );

        return (bool) $token;
    }

    public function isConsumable(string $tokenId): bool
    {
        $token = $this->connection->fetchOne(
            'SELECT consumed FROM payment_token WHERE token = :token',
            ['token' => $tokenId]
        );

        if (!$token) {
            return false;
        }

        return $token['consumed'] === 0;
    }
}
