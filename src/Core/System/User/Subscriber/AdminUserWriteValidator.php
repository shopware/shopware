<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Api\Controller\UserControllerTest
 */
#[Package('fundamentals@framework')]
class AdminUserWriteValidator implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $source = $event->getContext()->getSource();

        // no system scope exemption, the user and access key delete routes write in system scope
        if (!$source instanceof AdminApiSource || $source->isAdmin()) {
            return;
        }

        // integrations have no user, so a non-admin integration may not change any user or access key
        $callerId = $source->getUserId() !== null ? Uuid::fromHexToBytes($source->getUserId()) : null;

        $this->validateUsers($event, $callerId);
        $this->validateAccessKeys($event, $callerId);
    }

    /**
     * A non-admin may only change or delete their own user.
     */
    private function validateUsers(PreWriteValidationEvent $event, ?string $callerId): void
    {
        $userIds = [];
        foreach ($event->getExplicitCommandsForEntity(UserDefinition::ENTITY_NAME) as $command) {
            $userIds[] = $command->getPrimaryKey()['id'];
        }

        if ($userIds === []) {
            return;
        }

        // only existing users count, creating a new user is not restricted here
        $existingUserIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM `user` WHERE id IN (:userIds)',
            ['userIds' => $userIds],
            ['userIds' => ArrayParameterType::BINARY]
        );

        if (array_diff($existingUserIds, [$callerId]) !== []) {
            throw UserException::userChangeNotAllowed();
        }
    }

    /**
     * A non-admin may only create, change or delete their own access keys.
     * The user of an existing key can't be changed, `user_access_key.user_id` is immutable.
     */
    private function validateAccessKeys(PreWriteValidationEvent $event, ?string $callerId): void
    {
        $ownerIds = [];
        $existingKeyIds = [];
        foreach ($event->getExplicitCommandsForEntity(UserAccessKeyDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand) {
                $existingKeyIds[] = $command->getPrimaryKey()['id'];

                continue;
            }

            // a new key: the user it is created for
            if (isset($command->getPayload()['user_id'])) {
                $ownerIds[] = $command->getPayload()['user_id'];
            }
        }

        if ($existingKeyIds !== []) {
            // existing keys: the users they belong to
            $ownerIds = [...$ownerIds, ...$this->connection->fetchFirstColumn(
                'SELECT user_id FROM user_access_key WHERE id IN (:keyIds)',
                ['keyIds' => $existingKeyIds],
                ['keyIds' => ArrayParameterType::BINARY]
            )];
        }

        if (array_diff($ownerIds, [$callerId]) !== []) {
            throw UserException::userChangeNotAllowed();
        }
    }
}
