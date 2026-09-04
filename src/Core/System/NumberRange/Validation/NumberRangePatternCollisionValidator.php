<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @codeCoverageIgnore Tested via integration tests.
 *
 * @see \Shopware\Tests\Integration\Core\System\NumberRange\Validation\NumberRangePatternCollisionValidatorTest
 */
#[Package('framework')]
class NumberRangePatternCollisionValidator implements EventSubscriberInterface
{
    final public const NUMBER_RANGE_PATTERN_NOT_UNIQUE = 'NUMBER_RANGE_PATTERN_NOT_UNIQUE';

    /**
     * Document number range types are seeded with this technical name prefix, e.g. `document_invoice`.
     *
     * @see \Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX
     */
    private const DOCUMENT_TYPE_PREFIX = 'document_';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $commands = $this->collectRelevantCommands($event);
        if ($commands === []) {
            return;
        }

        $states = $this->resolveStates($commands);
        if ($states === []) {
            return;
        }

        $documentTypeNames = $this->fetchDocumentTypeNames(\array_values(\array_unique(\array_column($states, 'typeId'))));
        if ($documentTypeNames === []) {
            return;
        }

        $states = \array_filter($states, static fn (array $state): bool => isset($documentTypeNames[$state['typeId']]));
        if ($states === []) {
            return;
        }

        $existingPatternsByType = $this->fetchExistingPatternsByType(
            \array_values(\array_unique(\array_column($states, 'typeId'))),
            \array_keys($states),
        );

        $violations = new ConstraintViolationList();

        foreach ($states as $numberRangeId => $state) {
            $otherPatterns = $existingPatternsByType[$state['typeId']] ?? [];

            foreach ($states as $otherId => $otherState) {
                if ($otherId === $numberRangeId || $otherState['typeId'] !== $state['typeId']) {
                    continue;
                }

                $otherPatterns[] = $otherState['pattern'];
            }

            if (!\in_array($state['pattern'], $otherPatterns, true)) {
                continue;
            }

            $this->addViolation($violations, $commands[$numberRangeId]->getPath(), $documentTypeNames[$state['typeId']], $state['pattern']);
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @return array<string, WriteCommand>
     */
    private function collectRelevantCommands(PreWriteValidationEvent $event): array
    {
        $commands = [];

        foreach ($event->getCommandsForEntity(NumberRangeDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            if ($command instanceof UpdateCommand && !$command->hasAnyField('type_id', 'pattern')) {
                continue;
            }

            $commands[$command->getDecodedPrimaryKey()['id']] = $command;
        }

        return $commands;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return array<string, array{typeId: string, pattern: string}>
     */
    private function resolveStates(array $commands): array
    {
        $currentStates = $this->fetchCurrentStates($commands);
        $states = [];

        foreach ($commands as $numberRangeId => $command) {
            $payload = $command->getPayload();
            $currentState = $currentStates[$numberRangeId] ?? null;

            $typeId = \array_key_exists('type_id', $payload)
                ? $this->normalizeId($payload['type_id'])
                : ($currentState['typeId'] ?? null);

            $pattern = $payload['pattern'] ?? $currentState['pattern'] ?? null;

            if ($typeId === null || !\is_string($pattern) || $pattern === '') {
                continue;
            }

            $states[$numberRangeId] = [
                'typeId' => $typeId,
                'pattern' => $pattern,
            ];
        }

        return $states;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return array<string, array{typeId: string, pattern: string}>
     */
    private function fetchCurrentStates(array $commands): array
    {
        $numberRangeIds = [];
        foreach ($commands as $numberRangeId => $command) {
            if ($command instanceof UpdateCommand) {
                $numberRangeIds[] = $numberRangeId;
            }
        }

        if ($numberRangeIds === []) {
            return [];
        }

        /** @var list<array{id: string, type_id: string, pattern: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) as `id`, LOWER(HEX(`type_id`)) as `type_id`, `pattern`
             FROM `number_range`
             WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($numberRangeIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $states = [];
        foreach ($rows as $row) {
            $states[$row['id']] = [
                'typeId' => $row['type_id'],
                'pattern' => $row['pattern'],
            ];
        }

        return $states;
    }

    /**
     * @param list<string> $typeIds
     *
     * @return array<string, string> type id => document type name (e.g. "invoice")
     */
    private function fetchDocumentTypeNames(array $typeIds): array
    {
        if ($typeIds === []) {
            return [];
        }

        /** @var list<array{id: string, technical_name: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) as `id`, `technical_name`
             FROM `number_range_type`
             WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($typeIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $names = [];
        foreach ($rows as $row) {
            if (!\str_starts_with($row['technical_name'], self::DOCUMENT_TYPE_PREFIX)) {
                continue;
            }

            $names[$row['id']] = \substr($row['technical_name'], \strlen(self::DOCUMENT_TYPE_PREFIX));
        }

        return $names;
    }

    /**
     * @param list<string> $typeIds
     * @param list<string> $excludeNumberRangeIds
     *
     * @return array<string, list<string>> type id => patterns of other number ranges of that type
     */
    private function fetchExistingPatternsByType(array $typeIds, array $excludeNumberRangeIds): array
    {
        if ($typeIds === []) {
            return [];
        }

        /** @var list<array{id: string, type_id: string, pattern: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) as `id`, LOWER(HEX(`type_id`)) as `type_id`, `pattern`
             FROM `number_range`
             WHERE `type_id` IN (:typeIds)',
            ['typeIds' => Uuid::fromHexToBytesList($typeIds)],
            ['typeIds' => ArrayParameterType::BINARY],
        );

        $patternsByType = [];
        foreach ($rows as $row) {
            if (\in_array($row['id'], $excludeNumberRangeIds, true)) {
                continue;
            }

            $patternsByType[$row['type_id']][] = $row['pattern'];
        }

        return $patternsByType;
    }

    private function addViolation(ConstraintViolationList $violations, string $path, string $documentTypeName, string $pattern): void
    {
        $messageTemplate = 'Another {{ documentType }} number range already uses this pattern. Generated documents would collide.';
        $parameters = ['{{ documentType }}' => $documentTypeName];

        $violations->add(new ConstraintViolation(
            message: \str_replace(\array_keys($parameters), \array_values($parameters), $messageTemplate),
            messageTemplate: $messageTemplate,
            parameters: $parameters,
            root: null,
            propertyPath: $path . '/pattern',
            invalidValue: $pattern,
            code: self::NUMBER_RANGE_PATTERN_NOT_UNIQUE,
        ));
    }

    private function normalizeId(mixed $id): ?string
    {
        if (!\is_string($id)) {
            return null;
        }

        if (Uuid::isValid($id)) {
            return $id;
        }

        return Uuid::fromBytesToHex($id);
    }
}
