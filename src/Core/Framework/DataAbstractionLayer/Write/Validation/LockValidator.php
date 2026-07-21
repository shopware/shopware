<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Write\Validation\LockValidatorTest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see LockValidatorTest
 */
#[Package('framework')]
class LockValidator implements EventSubscriberInterface
{
    final public const VIOLATION_LOCKED = 'FRAMEWORK__ENTITY_IS_LOCKED';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
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

        if ($lockedEntities === []) {
            return;
        }

        $message = 'The %s entity is locked and can neither be modified nor deleted.';

        foreach ($lockedEntities as $entity => $_isLocked) {
            $violations->add(new ConstraintViolation(
                \sprintf($message, $entity),
                \sprintf($message, '{{ entity }}'),
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
     * @param WriteCommand[] $writeCommands
     *
     * @return array<string, bool>
     */
    private function containsLockedEntities(array $writeCommands): array
    {
        $ids = [];
        $locked = [];

        foreach ($writeCommands as $command) {
            if ($command instanceof InsertCommand) {
                continue;
            }

            $definition = $this->definitionRegistry->getByEntityName($command->getEntityName());

            if (!$definition->isLockAware()) {
                continue;
            }

            $lockedField = $definition->getField('locked');

            if (!$lockedField instanceof LockedField) {
                continue;
            }

            if (!$lockedField->lockTranslation() && $this->isTranslationUpdate($command, $definition, $writeCommands)) {
                continue;
            }

            $ids[$command->getEntityName()][] = $command->getPrimaryKey()['id'];
        }

        /** @var string $entityName */
        foreach ($ids as $entityName => $primaryKeys) {
            $locked[$entityName] = $this->connection->createQueryBuilder()
                ->select('1')
                ->from(EntityDefinitionQueryHelper::escape($entityName))
                ->where('`id` IN (:ids) AND `locked` = 1')
                ->setParameter('ids', $primaryKeys, ArrayParameterType::BINARY)
                ->executeQuery()
                ->rowCount() > 0;
        }

        return array_filter($locked);
    }

    /**
     * @param WriteCommand[] $writeCommands
     */
    private function isTranslationUpdate(WriteCommand $command, EntityDefinition $definition, array $writeCommands): bool
    {
        if (!$command instanceof UpdateCommand) {
            return false;
        }

        $payloadFields = array_keys($command->getPayload());

        $allowedFields = $definition->getFields()
            ->filter(fn (Field $field): bool => $field instanceof StorageAware && $field instanceof UpdatedAtField)
            ->map(static function (Field $field): string {
                \assert($field instanceof StorageAware);

                return $field->getStorageName();
            });

        if (array_diff($payloadFields, $allowedFields) !== []) {
            return false;
        }

        $translationDefinition = $definition->getTranslationDefinition();

        if ($translationDefinition === null) {
            return false;
        }

        $translationForeignKey = $definition->getEntityName() . '_id';

        foreach ($writeCommands as $writeCommand) {
            if ($writeCommand->getEntityName() !== $translationDefinition->getEntityName()) {
                continue;
            }

            if ($writeCommand->getPrimaryKey()[$translationForeignKey] !== $command->getPrimaryKey()['id']) {
                continue;
            }

            return true;
        }

        return false;
    }
}
