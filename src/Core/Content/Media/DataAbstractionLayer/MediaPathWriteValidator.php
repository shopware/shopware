<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\File\FileNameValidator;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Util\ExternalMediaUrl;
use Shopware\Core\Framework\Context;
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
 * Only the system may write relative paths; other writers may only reference external media by URL.
 *
 * @internal
 */
#[Package('discovery')]
class MediaPathWriteValidator implements EventSubscriberInterface
{
    private const PATH_ENTITIES = [MediaDefinition::ENTITY_NAME, MediaThumbnailDefinition::ENTITY_NAME];

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
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        foreach (self::PATH_ENTITIES as $entityName) {
            $this->validatePaths($event, $entityName);
        }
        $this->validateFileNames($event);
    }

    /**
     * @throws MediaException
     */
    public static function validateStorageValue(string $entityName, string $propertyName, mixed $value, bool $allowNullPath): void
    {
        if ($propertyName === 'path' && \in_array($entityName, self::PATH_ENTITIES, true)) {
            if ($value === null && $allowNullPath) {
                return;
            }

            if (!\is_string($value) || !ExternalMediaUrl::isStrict($value)) {
                throw MediaException::illegalPath();
            }

            return;
        }

        if ($propertyName === 'fileName' && $entityName === MediaDefinition::ENTITY_NAME && \is_string($value)) {
            (new FileNameValidator())->validateFileName($value);
        }
    }

    private function validatePaths(PreWriteValidationEvent $event, string $entityName): void
    {
        $commands = $this->commandsWriting($event, $entityName, 'path');
        $storedPaths = $this->fetchStoredValues($entityName, 'path', $commands);

        foreach ($commands as $command) {
            $path = $command->getPayload()['path'];

            if ($this->isUnchanged($command, $path, $storedPaths)) {
                continue;
            }

            $this->addViolationIfInvalid($event, $command, $entityName, 'path', $path);
        }
    }

    private function validateFileNames(PreWriteValidationEvent $event): void
    {
        $commands = array_filter(
            $this->commandsWriting($event, MediaDefinition::ENTITY_NAME, 'file_name'),
            static fn (WriteCommand $command): bool => \is_string($command->getPayload()['file_name'])
        );
        $storedFileNames = $this->fetchStoredValues(MediaDefinition::ENTITY_NAME, 'file_name', $commands);

        foreach ($commands as $command) {
            $fileName = $command->getPayload()['file_name'];

            if ($this->isUnchanged($command, $fileName, $storedFileNames)) {
                continue;
            }

            $this->addViolationIfInvalid($event, $command, MediaDefinition::ENTITY_NAME, 'fileName', $fileName);
        }
    }

    /**
     * Integrations write back entities they have read, and stored values may predate this validation.
     *
     * @param array<string, string|null> $storedValues
     */
    private function isUnchanged(WriteCommand $command, mixed $value, array $storedValues): bool
    {
        $id = $command->getDecodedPrimaryKey()['id'] ?? null;

        return $command instanceof UpdateCommand
            && \is_string($id)
            && \array_key_exists($id, $storedValues)
            && $storedValues[$id] === $value;
    }

    /**
     * @param array<WriteCommand> $commands
     *
     * @return array<string, string|null>
     */
    private function fetchStoredValues(string $entityName, string $column, array $commands): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (WriteCommand $command): ?string => $command instanceof UpdateCommand ? ($command->getPrimaryKey()['id'] ?? null) : null,
            $commands
        )));

        if ($ids === []) {
            return [];
        }

        /** @var array<string, string|null> $storedValues */
        $storedValues = $this->connection->fetchAllKeyValue(
            \sprintf('SELECT LOWER(HEX(`id`)), `%s` FROM `%s` WHERE `id` IN (:ids)', $column, $entityName),
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $storedValues;
    }

    /**
     * @return list<InsertCommand|UpdateCommand>
     */
    private function commandsWriting(PreWriteValidationEvent $event, string $entityName, string $column): array
    {
        return array_values(array_filter(
            $event->getCommands(),
            static fn (WriteCommand $command): bool => $command->getEntityName() === $entityName
                && ($command instanceof InsertCommand || $command instanceof UpdateCommand)
                && \array_key_exists($column, $command->getPayload())
        ));
    }

    private function addViolationIfInvalid(PreWriteValidationEvent $event, WriteCommand $command, string $entityName, string $propertyName, mixed $value): void
    {
        try {
            self::validateStorageValue($entityName, $propertyName, $value, allowNullPath: $command instanceof InsertCommand);
        } catch (MediaException $exception) {
            $this->addViolation($event, $command, $exception->getErrorCode(), '/' . $propertyName, $exception->getMessage());
        }
    }

    private function addViolation(PreWriteValidationEvent $event, WriteCommand $command, string $code, string $propertyPath, string $message): void
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation($message, $message, [], null, $propertyPath, null, code: $code));

        $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
    }
}
