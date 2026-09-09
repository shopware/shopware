<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @phpstan-type CurrentSalesChannelStates list<array<string, string>>
 */
#[Package('discovery')]
class SalesChannelValidator implements EventSubscriberInterface
{
    private const INSERT_VALIDATION_MESSAGE = 'The sales channel with id "%s" does not have a default sales channel language id in the language list.';
    private const INSERT_VALIDATION_CODE = 'SYSTEM__NO_GIVEN_DEFAULT_LANGUAGE_ID';

    private const UPDATE_VALIDATION_MESSAGE = 'Cannot update default language id because the given id is not in the language list of sales channel with id "%s"';
    private const UPDATE_VALIDATION_CODE = 'SYSTEM__CANNOT_UPDATE_DEFAULT_LANGUAGE_ID';

    private const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id from language list of the sales channel with id "%s".';
    private const DELETE_VALIDATION_CODE = 'SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID';

    private const CURRENCY_INSERT_VALIDATION_MESSAGE = 'The sales channel with id "%s" does not have a default sales channel currency id in the currency list.';
    private const CURRENCY_INSERT_VALIDATION_CODE = 'SYSTEM__NO_GIVEN_DEFAULT_CURRENCY_ID';

    private const CURRENCY_UPDATE_VALIDATION_MESSAGE = 'Cannot update default currency id because the given id is not in the currency list of sales channel with id "%s"';
    private const CURRENCY_UPDATE_VALIDATION_CODE = 'SYSTEM__CANNOT_UPDATE_DEFAULT_CURRENCY_ID';

    private const CURRENCY_DELETE_VALIDATION_MESSAGE = 'Cannot delete default currency id from currency list of the sales channel with id "%s".';
    private const CURRENCY_DELETE_VALIDATION_CODE = 'SYSTEM__CANNOT_DELETE_DEFAULT_CURRENCY_ID';

    /**
     * These sales channel types are not customer facing and are not required to assign their default currency to the
     * currency list, so the currency mapping validation is skipped for them.
     */
    private const CURRENCY_VALIDATION_EXCLUDED_TYPE_IDS = [
        Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON,
        Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE,
    ];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'handleSalesChannelLanguageIds',
        ];
    }

    public function handleSalesChannelLanguageIds(PreWriteValidationEvent $event): void
    {
        $this->validateMapping(
            event: $event,
            defaultField: 'language_id',
            mappingEntity: SalesChannelLanguageDefinition::ENTITY_NAME,
            mappingTable: 'sales_channel_language',
            mappingField: 'language_id',
            insertValidationMessage: self::INSERT_VALIDATION_MESSAGE,
            insertValidationCode: self::INSERT_VALIDATION_CODE,
            deleteValidationMessage: self::DELETE_VALIDATION_MESSAGE,
            deleteValidationCode: self::DELETE_VALIDATION_CODE,
            updateValidationMessage: self::UPDATE_VALIDATION_MESSAGE,
            updateValidationCode: self::UPDATE_VALIDATION_CODE,
        );

        $this->validateMapping(
            event: $event,
            defaultField: 'currency_id',
            mappingEntity: SalesChannelCurrencyDefinition::ENTITY_NAME,
            mappingTable: 'sales_channel_currency',
            mappingField: 'currency_id',
            insertValidationMessage: self::CURRENCY_INSERT_VALIDATION_MESSAGE,
            insertValidationCode: self::CURRENCY_INSERT_VALIDATION_CODE,
            deleteValidationMessage: self::CURRENCY_DELETE_VALIDATION_MESSAGE,
            deleteValidationCode: self::CURRENCY_DELETE_VALIDATION_CODE,
            updateValidationMessage: self::CURRENCY_UPDATE_VALIDATION_MESSAGE,
            updateValidationCode: self::CURRENCY_UPDATE_VALIDATION_CODE,
            excludedTypeIds: self::CURRENCY_VALIDATION_EXCLUDED_TYPE_IDS,
        );
    }

    /**
     * @param list<string> $excludedTypeIds
     */
    private function validateMapping(
        PreWriteValidationEvent $event,
        string $defaultField,
        string $mappingEntity,
        string $mappingTable,
        string $mappingField,
        string $insertValidationMessage,
        string $insertValidationCode,
        string $deleteValidationMessage,
        string $deleteValidationCode,
        string $updateValidationMessage,
        string $updateValidationCode,
        array $excludedTypeIds = [],
    ): void {
        $mapping = $this->extractMapping($event, $defaultField, $mappingEntity, $mappingField);
        if ($mapping->count() === 0) {
            return;
        }

        $states = $this->fetchCurrentStates($mapping->getKeys(), $defaultField, $mappingTable, $mappingField);
        $this->mergeCurrentStatesWithMapping($mapping, $states, $mappingField);
        $this->validateMappingData(
            mapping: $mapping,
            event: $event,
            insertValidationMessage: $insertValidationMessage,
            insertValidationCode: $insertValidationCode,
            deleteValidationMessage: $deleteValidationMessage,
            deleteValidationCode: $deleteValidationCode,
            updateValidationMessage: $updateValidationMessage,
            updateValidationCode: $updateValidationCode,
            excludedTypeIds: $excludedTypeIds,
        );
    }

    private function extractMapping(PreWriteValidationEvent $event, string $defaultField, string $mappingEntity, string $mappingField): Mapping
    {
        $mapping = new Mapping();
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() === SalesChannelDefinition::ENTITY_NAME) {
                $this->handleSalesChannelMapping($mapping, $command, $defaultField);

                continue;
            }

            if ($command->getEntityName() === $mappingEntity) {
                $this->handleSalesChannelMappingCommand($mapping, $command, $mappingField);
            }
        }

        return $mapping;
    }

    private function handleSalesChannelMapping(Mapping $mapping, WriteCommand $command, string $defaultField): void
    {
        if (!isset($command->getPayload()[$defaultField])) {
            return;
        }

        $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
        $salesChannelData = $mapping->get($id);
        if ($salesChannelData === null) {
            $salesChannelData = new SalesChannelData();
            $mapping->set($id, $salesChannelData);
        }

        if (isset($command->getPayload()['type_id'])) {
            $salesChannelData->typeId = Uuid::fromBytesToHex($command->getPayload()['type_id']);
        }

        if ($command instanceof UpdateCommand) {
            $salesChannelData->updateId = Uuid::fromBytesToHex($command->getPayload()[$defaultField]);

            return;
        }

        if (!$command instanceof InsertCommand) {
            return;
        }

        $salesChannelData->newDefault = Uuid::fromBytesToHex($command->getPayload()[$defaultField]);
        $salesChannelData->inserts = [];
    }

    private function handleSalesChannelMappingCommand(Mapping $mapping, WriteCommand $command, string $mappingField): void
    {
        $mappingId = Uuid::fromBytesToHex($command->getPrimaryKey()[$mappingField]);
        $id = Uuid::fromBytesToHex($command->getPrimaryKey()['sales_channel_id']);

        $salesChannelData = $mapping->get($id);
        if ($salesChannelData === null) {
            $salesChannelData = new SalesChannelData();
            $mapping->set($id, $salesChannelData);
        }

        if ($command instanceof DeleteCommand) {
            $salesChannelData->deletions[] = $mappingId;

            return;
        }

        if ($command instanceof InsertCommand) {
            $inserts = $salesChannelData->inserts ?? [];
            $inserts[] = $mappingId;
            $salesChannelData->inserts = $inserts;
        }
    }

    /**
     * @param list<string> $excludedTypeIds
     */
    private function validateMappingData(
        Mapping $mapping,
        PreWriteValidationEvent $event,
        string $insertValidationMessage,
        string $insertValidationCode,
        string $deleteValidationMessage,
        string $deleteValidationCode,
        string $updateValidationMessage,
        string $updateValidationCode,
        array $excludedTypeIds = [],
    ): void {
        $inserts = [];
        $deletions = [];
        $updates = [];

        foreach ($mapping as $salesChannelId => $salesChannelData) {
            if ($salesChannelData->typeId !== null && \in_array($salesChannelData->typeId, $excludedTypeIds, true)) {
                continue;
            }

            if ($salesChannelData->inserts !== null) {
                if ($this->isInvalidInsertCase($salesChannelData)) {
                    $inserts[$salesChannelId] = $salesChannelData->newDefault;
                }
            }

            $deletedDefault = $this->findDeletedDefaultMappingId($salesChannelData);
            if ($deletedDefault !== null) {
                $deletions[$salesChannelId] = $deletedDefault;
            }

            if ($salesChannelData->updateId !== null && $this->isInvalidUpdateCase($salesChannelData)) {
                $updates[$salesChannelId] = $salesChannelData->updateId;
            }
        }

        $this->writeViolationExceptions($inserts, $insertValidationMessage, $insertValidationCode, $event);
        $this->writeViolationExceptions($deletions, $deleteValidationMessage, $deleteValidationCode, $event);
        $this->writeViolationExceptions($updates, $updateValidationMessage, $updateValidationCode, $event);
    }

    /**
     * @phpstan-assert-if-true !null $salesChannelData->newDefault
     */
    private function isInvalidInsertCase(SalesChannelData $salesChannelData): bool
    {
        if ($salesChannelData->newDefault === null) {
            return false;
        }

        if ($salesChannelData->inserts === null) {
            throw SalesChannelException::invalidMappingOperation('Inserts are not allowed to be null while calling this method.');
        }

        return !\in_array($salesChannelData->newDefault, $salesChannelData->inserts, true);
    }

    private function isInvalidUpdateCase(SalesChannelData $salesChannelData): bool
    {
        $updateId = $salesChannelData->updateId;

        return !\in_array($updateId, $salesChannelData->state, true)
            && !($salesChannelData->newDefault === null && $updateId === $salesChannelData->currentDefault)
            && !($salesChannelData->inserts !== null && \in_array($updateId, $salesChannelData->inserts, true));
    }

    /**
     * Compares the deletions against the default mapping in effect after this write rather than the stored
     * one, so that assigning a new default and removing the previous one in a single write stays valid.
     */
    private function findDeletedDefaultMappingId(SalesChannelData $salesChannelData): ?string
    {
        $default = $salesChannelData->updateId ?? $salesChannelData->newDefault ?? $salesChannelData->currentDefault;

        if ($default === null || !\in_array($default, $salesChannelData->deletions, true)) {
            return null;
        }

        return $default;
    }

    /**
     * @param array<string, string> $invalidRecords
     */
    private function writeViolationExceptions(
        array $invalidRecords,
        string $messageTemplate,
        string $validationCode,
        PreWriteValidationEvent $event
    ): void {
        if (!$invalidRecords) {
            return;
        }

        $violations = new ConstraintViolationList();
        foreach (array_keys($invalidRecords) as $id) {
            $violations->add(new ConstraintViolation(
                \sprintf($messageTemplate, $id),
                \sprintf($messageTemplate, '{{ salesChannelId }}'),
                ['{{ salesChannelId }}' => $id],
                null,
                '/',
                null,
                null,
                $validationCode
            ));
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @param list<string> $salesChannelIds
     *
     * @return CurrentSalesChannelStates
     */
    private function fetchCurrentStates(array $salesChannelIds, string $defaultField, string $mappingTable, string $mappingField): array
    {
        /** @var CurrentSalesChannelStates $result */
        $result = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT LOWER(HEX(sales_channel.id)) AS sales_channel_id,
                LOWER(HEX(sales_channel.type_id)) AS type_id,
                LOWER(HEX(sales_channel.%s)) AS current_default,
                LOWER(HEX(mapping.%s)) AS %s
                FROM sales_channel
                LEFT JOIN %s mapping
                    ON mapping.sales_channel_id = sales_channel.id
                    WHERE sales_channel.id IN (:ids)',
                $defaultField,
                $mappingField,
                $mappingField,
                $mappingTable,
            ),
            ['ids' => Uuid::fromHexToBytesList($salesChannelIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $result;
    }

    /**
     * @param CurrentSalesChannelStates $states
     */
    private function mergeCurrentStatesWithMapping(Mapping $mapping, array $states, string $mappingField): void
    {
        if ($states === []) {
            return;
        }

        foreach ($states as $record) {
            $id = $record['sales_channel_id'];
            if (!$mapping->has($id)) {
                continue;
            }

            $salesChannelData = $mapping->get($id);

            if ($salesChannelData->typeId === null) {
                $salesChannelData->typeId = $record['type_id'];
            }
            $salesChannelData->currentDefault = $record['current_default'];
            $salesChannelData->state[] = $record[$mappingField];
            $salesChannelData->inserts = array_values(array_filter(
                $salesChannelData->inserts ?? [],
                static fn (string $value): bool => $value !== $record[$mappingField]
            ));

            if ($salesChannelData->inserts === []) {
                $salesChannelData->inserts = null;
            }
        }
    }
}
