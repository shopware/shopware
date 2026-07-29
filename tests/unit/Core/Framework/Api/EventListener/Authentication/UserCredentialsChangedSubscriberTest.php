<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\Authentication\UserCredentialsChangedSubscriber;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UserCredentialsChangedSubscriber::class)]
class UserCredentialsChangedSubscriberTest extends TestCase
{
    private const USER_ID = '018d9dcce00a704ab034ec0694981ed5';

    public function testDeactivatingUserInvalidatesTokens(): void
    {
        $subscriber = new UserCredentialsChangedSubscriber(
            $this->refreshTokenRepositoryExpectingRevocation(),
            $this->connectionExpectingTimestampUpdate(),
            new MockClock('2026-06-30 12:00:00')
        );

        $subscriber->onUserWritten($this->event([
            'id' => self::USER_ID,
            'active' => false,
        ]));
    }

    public function testActivatingUserDoesNotInvalidateTokens(): void
    {
        $refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $refreshTokenRepository->expects($this->never())->method('revokeRefreshTokensForUser');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('update');

        $subscriber = new UserCredentialsChangedSubscriber(
            $refreshTokenRepository,
            $connection,
            new MockClock('2026-06-30 12:00:00')
        );

        $subscriber->onUserWritten($this->event([
            'id' => self::USER_ID,
            'active' => true,
        ]));
    }

    public function testChangingPasswordStillInvalidatesTokens(): void
    {
        $subscriber = new UserCredentialsChangedSubscriber(
            $this->refreshTokenRepositoryExpectingRevocation(),
            $this->connectionExpectingTimestampUpdate(),
            new MockClock('2026-06-30 12:00:00')
        );

        $subscriber->onUserWritten($this->event([
            'id' => self::USER_ID,
            'password' => 'changed',
        ]));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function event(array $payload): EntityWrittenEvent
    {
        return new EntityWrittenEvent(
            UserDefinition::ENTITY_NAME,
            [new EntityWriteResult(self::USER_ID, $payload, UserDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE)],
            Context::createDefaultContext()
        );
    }

    private function refreshTokenRepositoryExpectingRevocation(): RefreshTokenRepository
    {
        $refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $refreshTokenRepository
            ->expects($this->once())
            ->method('revokeRefreshTokensForUser')
            ->with(self::USER_ID);

        return $refreshTokenRepository;
    }

    private function connectionExpectingTimestampUpdate(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with(
                'user',
                ['last_updated_password_at' => '2026-06-30 12:00:00.000'],
                ['id' => Uuid::fromHexToBytes(self::USER_ID)]
            );

        return $connection;
    }
}
