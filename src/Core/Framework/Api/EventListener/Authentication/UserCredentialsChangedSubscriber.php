<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserCredentialsChangedSubscriber implements EventSubscriberInterface
{
    /**
     * @var RefreshTokenRepository
     */
    private $refreshTokenRepository;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(RefreshTokenRepository $refreshTokenRepository, Connection $connection)
    {
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserEvents::USER_WRITTEN_EVENT => 'onUserWritten',
            UserEvents::USER_DELETED_EVENT => 'onUserDeleted',
        ];
    }

    public function onUserWritten(EntityWrittenEvent $event): void
    {
        $payloads = $event->getPayloads();

        foreach ($payloads as $payload) {
            if ($this->userCredentialsChanged($payload)) {
                $this->refreshTokenRepository->revokeRefreshTokensForUser($payload['id']);
                $this->updateLastUpdatedPasswordTimestamp($payload['id']);
            }
        }
    }

    public function onUserDeleted(EntityDeletedEvent $event): void
    {
        $ids = $event->getIds();

        foreach ($ids as $id) {
            $this->refreshTokenRepository->revokeRefreshTokensForUser($id);
        }
    }

    private function userCredentialsChanged(array $payload): bool
    {
        return isset($payload['password']);
    }

    private function updateLastUpdatedPasswordTimestamp(string $userId): void
    {
        $this->connection->update('user', [
            'last_updated_password_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [
            'id' => Uuid::fromHexToBytes($userId),
        ]);
    }
}
