<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Mirrors typed `document_base_config` columns and the deprecated `config`
 * JSON blob on every write, so legacy v1 readers (JSON-only) and new v2
 * readers (column-first) observe equivalent state without coordinating.
 * Active until the JSON column is dropped in v6.8.
 *
 * @internal
 */
#[Package('after-sales')]
class DocumentBaseConfigSyncSubscriber implements EventSubscriberInterface
{
    final public const DOCUMENT_CONFIG_MIGRATION_MAP = [
        'pageSize' => [
            'column' => 'page_size',
            'type' => 'string',
        ],
        'pageOrientation' => [
            'column' => 'page_orientation',
            'type' => 'string',
        ],
        'itemsPerPage' => [
            'column' => 'items_per_page',
            'type' => 'int',
        ],
        'displayHeader' => [
            'column' => 'display_header',
            'type' => 'bool',
        ],
        'displayFooter' => [
            'column' => 'display_footer',
            'type' => 'bool',
        ],
        'displayPageCount' => [
            'column' => 'display_page_count',
            'type' => 'bool',
        ],
        'displayCompanyAddress' => [
            'column' => 'display_company_address',
            'type' => 'bool',
        ],
        'displayReturnAddress' => [
            'column' => 'display_return_address',
            'type' => 'bool',
        ],
        'displayCustomerVatId' => [
            'column' => 'display_customer_vat_id',
            'type' => 'bool',
        ],
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'syncDocumentBaseConfig',
        ];
    }

    public function syncDocumentBaseConfig(EntityWriteEvent $event): void
    {
        $commands = $event->getCommandsForEntity(DocumentBaseConfigDefinition::ENTITY_NAME);

        if ($commands === []) {
            return;
        }

        foreach ($commands as $command) {
            if ($command instanceof DeleteCommand) {
                continue;
            }

            $this->syncCommand($command);
        }
    }

    private function syncCommand(WriteCommand $command): void
    {
        $payload = $command->getPayload();
        $columnsInPayload = [];

        foreach (self::DOCUMENT_CONFIG_MIGRATION_MAP as $jsonKey => $entry) {
            if ($command->hasField($entry['column'])) {
                $columnsInPayload[$jsonKey] = $payload[$entry['column']] ?? null;
            }
        }

        $hasColumnPayload = $columnsInPayload !== [];
        $hasConfigPayload = $command->hasField('config');

        $nothingToSync = !$hasColumnPayload && !$hasConfigPayload;

        if ($nothingToSync) {
            return;
        }

        $configChanged = false;
        $config = $this->resolveExistingConfig(
            $command,
            $payload,
            $hasConfigPayload,
            $hasColumnPayload
        );

        // For keys present in the payload, ensure the json config is updated to match the column values.
        foreach ($columnsInPayload as $jsonKey => $columnValue) {
            $jsonValue = $this->cast(
                self::DOCUMENT_CONFIG_MIGRATION_MAP[$jsonKey]['type'],
                $columnValue,
                asJson: true
            );

            $jsonValueDiffers = !\array_key_exists($jsonKey, $config) || $config[$jsonKey] !== $jsonValue;

            if ($jsonValueDiffers) {
                $config[$jsonKey] = $jsonValue;
                $configChanged = true;
            }
        }

        // For keys present only in json, mirror them to the typed column.
        foreach (self::DOCUMENT_CONFIG_MIGRATION_MAP as $jsonKey => $entry) {
            $columnAlreadyHandled = \array_key_exists($jsonKey, $columnsInPayload);
            $jsonHasKey = \array_key_exists($jsonKey, $config);

            if ($columnAlreadyHandled || !$jsonHasKey) {
                continue;
            }

            $command->addPayload(
                $entry['column'],
                $this->cast($entry['type'], $config[$jsonKey], asJson: false)
            );
        }

        if ($configChanged) {
            $command->addPayload('config', Json::encode($config));
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function resolveExistingConfig(WriteCommand $command, array $payload, bool $hasConfigPayload, bool $hasColumnPayload): array
    {
        if ($hasConfigPayload) {
            return $this->decodeConfig($payload['config'] ?? null);
        }

        $needsExistingFetch = $command instanceof UpdateCommand && $hasColumnPayload;

        if ($needsExistingFetch) {
            return $this->fetchExistingConfig($command);
        }

        return [];
    }

    /**
     * Partial v2 writes contain typed columns but no `config` payload.
     * The existing JSON must be merged with the new column values to avoid
     * clobbering keys (company info, plugin extensions) the caller didn't
     * touch — otherwise legacy readers would see those keys disappear.
     *
     * @return array<string, mixed>
     */
    private function fetchExistingConfig(UpdateCommand $command): array
    {
        $id = $command->getPrimaryKey()['id'] ?? null;

        if (!\is_string($id)) {
            return [];
        }

        $raw = $this->connection->fetchOne(
            'SELECT `config` FROM `document_base_config` WHERE `id` = :id LIMIT 1',
            ['id' => $id],
        );

        return $this->decodeConfig(\is_string($raw) ? $raw : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeConfig(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = \json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    private function cast(string $type, mixed $value, bool $asJson): string|int|bool|null
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool' => $asJson ? (bool) $value : ($value ? 1 : 0),
            'int' => (int) $value,
            'string' => (string) $value,
            default => throw DocumentV2Exception::unsupportedConfigCastType($type),
        };
    }
}
