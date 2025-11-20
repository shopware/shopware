<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\Token;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(PaymentTokenLifecycle::class)]
#[Package('checkout')]
class PaymentTokenLifecycleTest extends TestCase
{
    #[DisabledFeatures(['REPEATED_PAYMENT_FINALIZE'])]
    public function testInvalidateTokenDeletesWhenFeatureDisabled(): void
    {
        $tokenId = 'token-1';

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('delete')
            ->with('payment_token', ['token' => $tokenId]);

        // ensure update is not called
        $connection->expects($this->never())->method('update');

        $lifecycle = new PaymentTokenLifecycle($connection);

        $result = $lifecycle->invalidateToken($tokenId);
        static::assertFalse($result);
    }

    public function testInvalidateTokenMarksConsumedWhenFeatureEnabled(): void
    {
        $tokenId = 'token-2';

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('update')
            ->with('payment_token', ['consumed' => 1], ['token' => $tokenId]);

        // ensure delete is not called
        $connection->expects($this->never())->method('delete');

        $lifecycle = new PaymentTokenLifecycle($connection);

        $result = $lifecycle->invalidateToken($tokenId);
        static::assertFalse($result);
    }

    public function testAddTokenInsertsWithFormattedExpires(): void
    {
        $tokenId = 'token-3';
        $expires = new \DateTimeImmutable('2020-01-02 03:04:05');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('insert')
            ->with('payment_token', [
                'token' => $tokenId,
                'expires' => $expires->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

        $lifecycle = new PaymentTokenLifecycle($connection);
        $lifecycle->addToken($tokenId, $expires);
    }

    public function testIsRegisteredReturnsTrueAndFalse(): void
    {
        $tokenId = 'token-4';

        // case true
        $connectionTrue = $this->createMock(Connection::class);
        $connectionTrue->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT 1 FROM payment_token WHERE token = :token', ['token' => $tokenId])
            ->willReturn('1');

        $lifecycleTrue = new PaymentTokenLifecycle($connectionTrue);
        static::assertTrue($lifecycleTrue->isRegistered($tokenId));

        // case false
        $connectionFalse = $this->createMock(Connection::class);
        $connectionFalse->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT 1 FROM payment_token WHERE token = :token', ['token' => $tokenId])
            ->willReturn(false);

        $lifecycleFalse = new PaymentTokenLifecycle($connectionFalse);
        static::assertFalse($lifecycleFalse->isRegistered($tokenId));
    }

    public function testIsConsumableHandlesMissingAndConsumedStates(): void
    {
        $tokenId = 'token-5';

        // missing token -> false
        $connMissing = $this->createMock(Connection::class);
        $connMissing->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT consumed FROM payment_token WHERE token = :token', ['token' => $tokenId])
            ->willReturn(false);

        $lifecycleMissing = new PaymentTokenLifecycle($connMissing);
        static::assertFalse($lifecycleMissing->isConsumable($tokenId));

        // consumed = 0 -> true
        $connConsumable = $this->createMock(Connection::class);
        $connConsumable->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT consumed FROM payment_token WHERE token = :token', ['token' => $tokenId])
            ->willReturn(['consumed' => 0]);

        $lifecycleConsumable = new PaymentTokenLifecycle($connConsumable);
        static::assertTrue($lifecycleConsumable->isConsumable($tokenId));

        // consumed = 1 -> false
        $connNotConsumable = $this->createMock(Connection::class);
        $connNotConsumable->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT consumed FROM payment_token WHERE token = :token', ['token' => $tokenId])
            ->willReturn(['consumed' => 1]);

        $lifecycleNotConsumable = new PaymentTokenLifecycle($connNotConsumable);
        static::assertFalse($lifecycleNotConsumable->isConsumable($tokenId));
    }
}
