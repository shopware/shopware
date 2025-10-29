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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @phpstan-type Mapping       array<string, array{new_default?: string, inserts?: list<string>, updateId?: string, deletions?: list<string>, state?: array{}}>
 * @phpstan-type ChannelData                 array{new_default?: string, inserts?: list<string>, updateId?: string, deletions?: list<string>, state?: list<string>, current_default?: string}
 * @phpstan-type MergedMapping array<string, ChannelData>
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

        if (!$mapping) {
            return;
        }

        $salesChannelIds = array_keys($mapping);
        $states = $this->fetchCurrentLanguageStates($salesChannelIds);

        $mapping = $this->mergeCurrentStatesWithMapping($mapping, $states);

        $this->validateLanguages($mapping, $event);
    }

    /**
     * @return Mapping
     */
    private function extractMapping(PreWriteValidationEvent $event): array
    {
        $mapping = [];
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() === SalesChannelDefinition::ENTITY_NAME) {
                $mapping = $this->handleSalesChannelMapping($mapping, $command);

                continue;
            }

            if ($command->getEntityName() === SalesChannelLanguageDefinition::ENTITY_NAME) {
                $mapping = $this->handleSalesChannelLanguageMapping($mapping, $command);
            }
        }

        return $mapping;
    }

    /**
     * @param Mapping $mapping
     *
     * @return array<string, array{updateId?: string, new_default?: string, inserts?: array{}, state?: array{}}>
     */
    private function handleSalesChannelMapping(array $mapping, WriteCommand $command): array
    {
        if (!isset($command->getPayload()['language_id'])) {
            return $mapping;
        }

        $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);

        if ($command instanceof UpdateCommand) {
            $mapping[$id]['updateId'] = Uuid::fromBytesToHex($command->getPayload()['language_id']);

            return $mapping;
        }

        if (!$command instanceof InsertCommand || !$this->isSupportedSalesChannelType($command)) {
            return $mapping;
        }

        $mapping[$id]['new_default'] = Uuid::fromBytesToHex($command->getPayload()['language_id']);
        $mapping[$id]['inserts'] = [];
        $mapping[$id]['state'] = [];

        return $mapping;
    }

    private function isSupportedSalesChannelType(WriteCommand $command): bool
    {
        $typeId = Uuid::fromBytesToHex($command->getPayload()['type_id']);

        return $typeId === Defaults::SALES_CHANNEL_TYPE_STOREFRONT
            || $typeId === Defaults::SALES_CHANNEL_TYPE_API;
    }

    /**
     * @param Mapping $mapping
     *
     * @return array<string, array{state: array{}, deletions?: list<string>, inserts?: list<string>}>
     */
    private function handleSalesChannelLanguageMapping(array $mapping, WriteCommand $command): array
    {
        $language = Uuid::fromBytesToHex($command->getPrimaryKey()['language_id']);
        $id = Uuid::fromBytesToHex($command->getPrimaryKey()['sales_channel_id']);

        $mapping[$id]['state'] = [];

        if ($command instanceof DeleteCommand) {
            $mapping[$id]['deletions'][] = $language;

            return $mapping;
        }

        if ($command instanceof InsertCommand) {
            $mapping[$id]['inserts'][] = $language;
        }

        return $mapping;
    }

    /**
     * @param MergedMapping $mapping
     */
    private function validateLanguages(array $mapping, PreWriteValidationEvent $event): void
    {
        $inserts = [];
        $duplicates = [];
        $deletions = [];
        $updates = [];

        foreach ($mapping as $id => $channel) {
            if (isset($channel['inserts'])) {
                if (isset($channel['new_default']) && $this->isInvalidInsertCase($channel)) {
                    $inserts[$id] = $channel['new_default'];
                }

                $duplicatedIds = $this->getDuplicates($channel);

                if ($duplicatedIds !== []) {
                    $duplicates[$id] = $duplicatedIds;
                }
            }

            if (isset($channel['deletions'], $channel['current_default']) && $this->isInvalidDeleteCase($channel)) {
                $deletions[$id] = $channel['current_default'];
            }

            if (isset($channel['updateId']) && $this->isInvalidUpdateCase($channel)) {
                $updates[$id] = $channel['updateId'];
            }
        }

        $this->writeDuplicateViolationExceptions($duplicates, $event);
        $this->writeViolationExceptions($inserts, self::INSERT_VALIDATION_MESSAGE, self::INSERT_VALIDATION_CODE, $event);
        $this->writeViolationExceptions($deletions, self::DELETE_VALIDATION_MESSAGE, self::DELETE_VALIDATION_CODE, $event);
        $this->writeViolationExceptions($updates, self::UPDATE_VALIDATION_MESSAGE, self::UPDATE_VALIDATION_CODE, $event);
    }

    /**
     * @param array{new_default: string, inserts: list<string>, updateId?: string, deletions?: list<string>, state?: list<string>, current_default?: string} $channel
     */
    private function isInvalidInsertCase(array $channel): bool
    {
        return !\in_array($channel['new_default'], $channel['inserts'], true);
    }

    /**
     * @param array{new_default?: string, inserts?: list<string>, updateId: string, deletions?: list<string>, state?: list<string>, current_default?: string} $channel
     */
    private function isInvalidUpdateCase(array $channel): bool
    {
        $updateId = $channel['updateId'];

        return !\in_array($updateId, $channel['state'] ?? [], true)
            && !(empty($channel['new_default']) && $updateId === ($channel['current_default'] ?? null))
            && !(isset($channel['inserts']) && \in_array($updateId, $channel['inserts'], true));
    }

    /**
     * @param array{new_default?: string, inserts?: list<string>, updateId?: string, deletions: list<string>, state?: list<string>, current_default: string} $channel
     */
    private function isInvalidDeleteCase(array $channel): bool
    {
        return \in_array($channel['current_default'], $channel['deletions'], true);
    }

    /**
     * @param array{new_default?: string, inserts: list<string>, updateId?: string, deletions?: list<string>, state?: list<string>, current_default?: string} $channel
     *
     * @return list<string>
     */
    private function getDuplicates(array $channel): array
    {
        return array_values(array_intersect($channel['state'] ?? [], $channel['inserts']));
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
     * @param Mapping $mapping
     * @param CurrentLanguageStates $states
     *
     * @return MergedMapping
     */
    private function mergeCurrentStatesWithMapping(array $mapping, array $states): array
    {
        if ($states === []) {
            return $mapping;
        }

        foreach ($states as $record) {
            $id = $record['sales_channel_id'];
            if (!\array_key_exists($id, $mapping)) {
                continue;
            }

            $mapping[$id]['current_default'] = $record['current_default'];
            $mapping[$id]['state'][] = $record['language_id'];
            $mapping[$id]['inserts'] = array_values(array_filter(
                $mapping[$id]['inserts'] ?? [],
                static fn ($value) => $value !== $record['language_id']
            ));
            if (empty($mapping[$id]['inserts'])) {
                unset($mapping[$id]['inserts']);
            }
        }

        return $mapping;
    }
}
