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
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @phpstan-type CurrentLanguageStates list<array{sales_channel_id: string, current_default: string, language_id: string}>
 */
#[Package('discovery')]
class SalesChannelValidator implements EventSubscriberInterface
{
    private const INSERT_VALIDATION_MESSAGE = 'The sales channel with id "%s" does not have a default sales channel language id in the language list.';
    private const INSERT_VALIDATION_CODE = 'SYSTEM__NO_GIVEN_DEFAULT_LANGUAGE_ID';

    private const DUPLICATED_ENTRY_VALIDATION_MESSAGE = 'The sales channel language "%s" for the sales channel "%s" already exists.';
    private const DUPLICATED_ENTRY_VALIDATION_CODE = 'SYSTEM__DUPLICATED_SALES_CHANNEL_LANGUAGE';

    private const UPDATE_VALIDATION_MESSAGE = 'Cannot update default language id because the given id is not in the language list of sales channel with id "%s"';
    private const UPDATE_VALIDATION_CODE = 'SYSTEM__CANNOT_UPDATE_DEFAULT_LANGUAGE_ID';

    private const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id from language list of the sales channel with id "%s".';
    private const DELETE_VALIDATION_CODE = 'SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID';

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
        $mapping = $this->extractMapping($event);

        if ($mapping->count() === 0) {
            return;
        }

        $salesChannelIds = $mapping->getKeys();
        $states = $this->fetchCurrentLanguageStates($salesChannelIds);

        $this->mergeCurrentStatesWithMapping($mapping, $states);

        $this->validateLanguages($mapping, $event);
    }

    private function extractMapping(PreWriteValidationEvent $event): Mapping
    {
        $mapping = new Mapping();
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() === SalesChannelDefinition::ENTITY_NAME) {
                $this->handleSalesChannelMapping($mapping, $command);

                continue;
            }

            if ($command->getEntityName() === SalesChannelLanguageDefinition::ENTITY_NAME) {
                $this->handleSalesChannelLanguageMapping($mapping, $command);
            }
        }

        return $mapping;
    }

    private function handleSalesChannelMapping(Mapping $mapping, WriteCommand $command): void
    {
        if (!isset($command->getPayload()['language_id'])) {
            return;
        }

        $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
        $salesChannelData = $mapping->get($id);
        if ($salesChannelData === null) {
            $salesChannelData = new SalesChannelData();
            $mapping->set($id, $salesChannelData);
        }

        if ($command instanceof UpdateCommand) {
            $salesChannelData->updateId = Uuid::fromBytesToHex($command->getPayload()['language_id']);

            return;
        }

        if (!$command instanceof InsertCommand || !$this->isSupportedSalesChannelType($command)) {
            return;
        }

        $salesChannelData->newDefault = Uuid::fromBytesToHex($command->getPayload()['language_id']);
        $salesChannelData->inserts = [];
    }

    private function isSupportedSalesChannelType(WriteCommand $command): bool
    {
        $typeId = Uuid::fromBytesToHex($command->getPayload()['type_id']);

        return $typeId === Defaults::SALES_CHANNEL_TYPE_STOREFRONT
            || $typeId === Defaults::SALES_CHANNEL_TYPE_API;
    }

    private function handleSalesChannelLanguageMapping(Mapping $mapping, WriteCommand $command): void
    {
        $language = Uuid::fromBytesToHex($command->getPrimaryKey()['language_id']);
        $id = Uuid::fromBytesToHex($command->getPrimaryKey()['sales_channel_id']);

        $salesChannelData = $mapping->get($id);
        if ($salesChannelData === null) {
            $salesChannelData = new SalesChannelData();
            $mapping->set($id, $salesChannelData);
        }

        if ($command instanceof DeleteCommand) {
            $salesChannelData->deletions[] = $language;

            return;
        }

        if ($command instanceof InsertCommand) {
            $inserts = $salesChannelData->inserts ?? [];
            $inserts[] = $language;
            $salesChannelData->inserts = $inserts;
        }
    }

    private function validateLanguages(Mapping $mapping, PreWriteValidationEvent $event): void
    {
        $inserts = [];
        $duplicates = [];
        $deletions = [];
        $updates = [];

        foreach ($mapping as $salesChannelId => $salesChannelData) {
            if ($salesChannelData->inserts !== null) {
                if ($this->isInvalidInsertCase($salesChannelData)) {
                    $inserts[$salesChannelId] = $salesChannelData->newDefault;
                }

                $duplicatedIds = $this->getDuplicates($salesChannelData);

                if ($duplicatedIds !== []) {
                    $duplicates[$salesChannelId] = $duplicatedIds;
                }
            }

            if ($salesChannelData->deletions !== [] && $this->isInvalidDeleteCase($salesChannelData)) {
                $deletions[$salesChannelId] = $salesChannelData->currentDefault;
            }

            if ($salesChannelData->updateId !== null && $this->isInvalidUpdateCase($salesChannelData)) {
                $updates[$salesChannelId] = $salesChannelData->updateId;
            }
        }

        $this->writeDuplicateViolationExceptions($duplicates, $event);
        $this->writeViolationExceptions($inserts, self::INSERT_VALIDATION_MESSAGE, self::INSERT_VALIDATION_CODE, $event);
        $this->writeViolationExceptions($deletions, self::DELETE_VALIDATION_MESSAGE, self::DELETE_VALIDATION_CODE, $event);
        $this->writeViolationExceptions($updates, self::UPDATE_VALIDATION_MESSAGE, self::UPDATE_VALIDATION_CODE, $event);
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
     * @phpstan-assert-if-true !null $salesChannelData->currentDefault
     */
    private function isInvalidDeleteCase(SalesChannelData $salesChannelData): bool
    {
        if ($salesChannelData->currentDefault === null) {
            return false;
        }

        return \in_array($salesChannelData->currentDefault, $salesChannelData->deletions, true);
    }

    /**
     * @return list<string>
     */
    private function getDuplicates(SalesChannelData $salesChannelData): array
    {
        if ($salesChannelData->inserts === null) {
            throw SalesChannelException::invalidMappingOperation('Inserts are not allowed to be null while calling this method.');
        }

        return array_values(array_intersect($salesChannelData->state, $salesChannelData->inserts));
    }

    /**
     * @param array<string, list<string>> $duplicates
     */
    private function writeDuplicateViolationExceptions(array $duplicates, PreWriteValidationEvent $event): void
    {
        if (!$duplicates) {
            return;
        }

        $violations = new ConstraintViolationList();

        foreach ($duplicates as $id => $duplicateLanguages) {
            foreach ($duplicateLanguages as $languageId) {
                $violations->add(new ConstraintViolation(
                    \sprintf(self::DUPLICATED_ENTRY_VALIDATION_MESSAGE, $languageId, $id),
                    \sprintf(self::DUPLICATED_ENTRY_VALIDATION_MESSAGE, '{{ languageId }}', '{{ salesChannelId }}'),
                    [
                        '{{ salesChannelId }}' => $id,
                        '{{ languageId }}' => $languageId,
                    ],
                    null,
                    '/',
                    null,
                    null,
                    self::DUPLICATED_ENTRY_VALIDATION_CODE
                ));
            }
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
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
     * @return CurrentLanguageStates
     */
    private function fetchCurrentLanguageStates(array $salesChannelIds): array
    {
        /** @var CurrentLanguageStates $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(sales_channel.id)) AS sales_channel_id,
            LOWER(HEX(sales_channel.language_id)) AS current_default,
            LOWER(HEX(mapping.language_id)) AS language_id
            FROM sales_channel
            LEFT JOIN sales_channel_language mapping
                ON mapping.sales_channel_id = sales_channel.id
                WHERE sales_channel.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($salesChannelIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $result;
    }

    /**
     * @param CurrentLanguageStates $states
     */
    private function mergeCurrentStatesWithMapping(Mapping $mapping, array $states): void
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

            $salesChannelData->currentDefault = $record['current_default'];
            $salesChannelData->state[] = $record['language_id'];
            $salesChannelData->inserts = array_values(array_filter(
                $salesChannelData->inserts ?? [],
                static fn (string $value): bool => $value !== $record['language_id']
            ));

            if ($salesChannelData->inserts === []) {
                $salesChannelData->inserts = null;
            }
        }
    }
}
