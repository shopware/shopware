<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class LockValidator implements EventSubscriberInterface
{
    public const VIOLATION_LOCKED = 'FRAMEWORK__ENTITY_IS_LOCKED';

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    /**
     * @throws WriteConstraintViolationException
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $violations = new ConstraintViolationList();
        $writeCommands = $event->getCommands();
        $lockedEntities = $this->containsLockedEntities($writeCommands);

        if (empty($lockedEntities)) {
            return;
        }

        $message = 'The %s entity is locked and can neither be modified nor deleted.';

        foreach ($lockedEntities as $entity => $_isLocked) {
            $violations->add(new ConstraintViolation(
                sprintf($message, $entity),
                sprintf($message, '{{ entity }}'),
                ['{{ entity }}' => $entity],
                null,
                '/',
                null,
                null,
                self::VIOLATION_LOCKED
            ));
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @param WriteCommandInterface[] $writeCommands
     */
    private function containsLockedEntities(array $writeCommands): array
    {
        $ids = [];
        $locked = [];

        foreach ($writeCommands as $command) {
            if ($command instanceof InsertCommand) {
                continue;
            }

            if (!$command->getDefinition()->isLockAware()) {
                continue;
            }

            $ids[$command->getDefinition()->getEntityName()][] = $command->getPrimaryKey()['id'];
        }

        foreach ($ids as $entityName => $primaryKeys) {
            $locked[$entityName] = $this->connection->createQueryBuilder()
                ->select('1')
                ->from(EntityDefinitionQueryHelper::escape($entityName))
                ->where('`id` IN (:ids) AND `locked` = 1')
                ->setParameter('ids', $primaryKeys, Connection::PARAM_STR_ARRAY)
                ->execute()
                ->rowCount() > 0;
        }

        return array_filter($locked);
    }
}
