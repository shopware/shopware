<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Services are owned by the internal service lifecycle, which always runs in the system scope.
 *
 * This subscriber prohibits creating, modifying or deleting a service through the auto-generated
 * entity API (Admin and Sync), which writes in the crud scope and would otherwise bypass the
 * lifecycle and its requirement gate.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Service\Subscriber\ServiceWriteProtectionSubscriberTest
 */
#[Package('framework')]
readonly class ServiceWriteProtectionSubscriber implements EventSubscriberInterface
{
    final public const VIOLATION_NO_PERMISSION = 'service_write_not_allowed_violation';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'checkWrite',
        ];
    }

    public function checkWrite(PreWriteValidationEvent $event): void
    {
        // Every legitimate service mutation runs in the system scope (ServiceLifecycle elevates to it),
        // so a non-system write can never be the internal lifecycle. This also skips most writes cheaply.
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        $appCommands = $event->getCommandsForEntity(AppDefinition::ENTITY_NAME);
        if ($appCommands === []) {
            // The write does not touch the app entity at all , bail
            return;
        }

        $serviceIds = $this->fetchServiceIds($appCommands);

        $violations = new ConstraintViolationList(array_map(
            $this->buildViolation(...),
            array_filter($appCommands, fn (WriteCommand $command): bool => $this->isProtected($command, $serviceIds))
        ));

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @param array<string> $selfManagedIds
     */
    private function isProtected(WriteCommand $command, array $selfManagedIds): bool
    {
        // creating a service or changing an app to a service is a lifecycle-only operation.
        if (($command instanceof InsertCommand || $command instanceof UpdateCommand)
            && ($command->getPayload()['self_managed'] ?? false)) {
            return true;
        }

        // modifying or deleting a row that is already a service.
        if ($command instanceof UpdateCommand || $command instanceof DeleteCommand) {
            return \in_array($command->getPrimaryKey()['id'], $selfManagedIds, true);
        }

        return false;
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return array<string>
     */
    private function fetchServiceIds(array $commands): array
    {
        $ids = array_map(static fn (WriteCommand $command): string => $command->getPrimaryKey()['id'], $commands);

        return $this->connection->fetchFirstColumn(
            'SELECT `id` FROM `app` WHERE `id` IN (:ids) AND `self_managed` = 1',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    private function buildViolation(WriteCommand $command): ConstraintViolation
    {
        return new ConstraintViolation(
            'A service cannot be created, modified or deleted through the API.',
            'A service cannot be created, modified or deleted through the API.',
            [],
            null,
            '/' . $command->getEntityName(),
            null,
            null,
            self::VIOLATION_NO_PERMISSION
        );
    }
}
