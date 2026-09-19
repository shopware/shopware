<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Validation;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore Tested via integration tests.
 *
 * @see \Shopware\Tests\Integration\Core\System\NumberRange\Validation\NumberRangePatternCollisionValidatorTest
 *
 * @phpstan-type WrittenPattern array{typeId: string, pattern: string}
 */
#[Package('framework')]
final readonly class NumberRangePatternCollisionValidator implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<NumberRangeCollection> $numberRangeRepository
     * @param EntityRepository<NumberRangeTypeCollection> $numberRangeTypeRepository
     */
    public function __construct(
        private EntityRepository $numberRangeRepository,
        private EntityRepository $numberRangeTypeRepository,
        private LoggerInterface $logger,
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

        $writtenPatterns = $this->resolveWrittenPatterns($commands, $event->getContext());
        if ($writtenPatterns === []) {
            return;
        }

        $documentTypeNames = $this->fetchDocumentTypeNames(
            $this->collectTypeIds($writtenPatterns),
            $event->getContext(),
        );
        if ($documentTypeNames === []) {
            return;
        }

        $writtenPatterns = \array_filter(
            $writtenPatterns,
            static fn (array $written): bool => isset($documentTypeNames[$written['typeId']]),
        );
        if ($writtenPatterns === []) {
            return;
        }

        $existingPatternsByType = $this->fetchExistingPatternsByType(
            $this->collectTypeIds($writtenPatterns),
            \array_keys($writtenPatterns),
            $event->getContext(),
        );

        foreach ($writtenPatterns as $numberRangeId => $written) {
            $conflictingPatterns = $existingPatternsByType[$written['typeId']] ?? [];

            foreach ($writtenPatterns as $otherId => $otherWritten) {
                if ($otherId === $numberRangeId || $otherWritten['typeId'] !== $written['typeId']) {
                    continue;
                }

                $conflictingPatterns[] = $otherWritten['pattern'];
            }

            if (!\in_array($written['pattern'], $conflictingPatterns, true)) {
                continue;
            }

            $this->logger->warning(
                'Number range "{numberRangeId}" uses pattern "{pattern}", already used by another {documentType} number range. Generated documents may collide.',
                [
                    'numberRangeId' => $numberRangeId,
                    'pattern' => $written['pattern'],
                    'documentType' => $documentTypeNames[$written['typeId']],
                ],
            );
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
     * Merges the written payload over the persisted row, so a partial update is judged on its resulting state.
     *
     * @param array<string, WriteCommand> $commands
     *
     * @return array<string, WrittenPattern>
     */
    private function resolveWrittenPatterns(array $commands, Context $context): array
    {
        $persistedPatterns = $this->fetchPersistedPatterns($commands, $context);
        $writtenPatterns = [];

        foreach ($commands as $numberRangeId => $command) {
            $payload = $command->getPayload();
            $persisted = $persistedPatterns[$numberRangeId] ?? null;

            $typeId = \array_key_exists('type_id', $payload)
                ? $this->normalizeId($payload['type_id'])
                : ($persisted['typeId'] ?? null);

            $pattern = $payload['pattern'] ?? $persisted['pattern'] ?? null;

            if ($typeId === null || !\is_string($pattern) || $pattern === '') {
                continue;
            }

            $writtenPatterns[$numberRangeId] = [
                'typeId' => $typeId,
                'pattern' => $pattern,
            ];
        }

        return $writtenPatterns;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return array<string, WrittenPattern>
     */
    private function fetchPersistedPatterns(array $commands, Context $context): array
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

        $numberRanges = $this->numberRangeRepository
            ->search(new Criteria($numberRangeIds), $context)
            ->getEntities();

        $persistedPatterns = [];
        foreach ($numberRanges as $numberRange) {
            $pattern = $numberRange->getPattern();
            $typeId = $numberRange->getTypeId();

            if ($pattern === null || $typeId === null) {
                continue;
            }

            $persistedPatterns[$numberRange->getId()] = [
                'typeId' => $typeId,
                'pattern' => $pattern,
            ];
        }

        return $persistedPatterns;
    }

    /**
     * @param list<string> $typeIds
     *
     * @return array<string, string> number range type id => document type name (e.g. "invoice")
     */
    private function fetchDocumentTypeNames(array $typeIds, Context $context): array
    {
        if ($typeIds === []) {
            return [];
        }

        $types = $this->numberRangeTypeRepository
            ->search(new Criteria($typeIds), $context)
            ->getEntities();

        $prefix = DocumentNumberGenerator::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX;

        $documentTypeNames = [];
        foreach ($types as $type) {
            $technicalName = $type->getTechnicalName();

            if ($technicalName === null || !\str_starts_with($technicalName, $prefix)) {
                continue;
            }

            $documentTypeNames[$type->getId()] = \substr($technicalName, \strlen($prefix));
        }

        return $documentTypeNames;
    }

    /**
     * @param list<string> $typeIds
     * @param list<string> $excludeNumberRangeIds
     *
     * @return array<string, list<string>> number range type id => patterns of the other number ranges of that type
     */
    private function fetchExistingPatternsByType(array $typeIds, array $excludeNumberRangeIds, Context $context): array
    {
        if ($typeIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('typeId', $typeIds));

        $numberRanges = $this->numberRangeRepository->search($criteria, $context)->getEntities();

        $patternsByType = [];
        foreach ($numberRanges as $numberRange) {
            $pattern = $numberRange->getPattern();
            $typeId = $numberRange->getTypeId();

            if ($pattern === null || $typeId === null || \in_array($numberRange->getId(), $excludeNumberRangeIds, true)) {
                continue;
            }

            $patternsByType[$typeId][] = $pattern;
        }

        return $patternsByType;
    }

    /**
     * @param array<string, WrittenPattern> $writtenPatterns
     *
     * @return list<string>
     */
    private function collectTypeIds(array $writtenPatterns): array
    {
        return \array_values(\array_unique(\array_column($writtenPatterns, 'typeId')));
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
