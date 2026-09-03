<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentBaseConfigValidator implements EventSubscriberInterface
{
    final public const INVALID_PAYMENT_DUE_DATE = 'DOCUMENT_BASE_CONFIG_INVALID_PAYMENT_DUE_DATE';

    final public const DUPLICATE_FILENAME_INFIX = 'DOCUMENT_BASE_CONFIG_DUPLICATE_FILENAME_INFIX';

    private const SCOPE_FIELDS = ['type_name', 'document_type_id', 'global'];

    /**
     * @param \Closure(): DocumentRendererRegistry $documentRendererRegistry
     */
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly Connection $connection,
        private readonly DocumentTypeRegistry $documentTypeRegistry,
        private readonly \Closure $documentRendererRegistry,
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
        $violations = new ConstraintViolationList();

        foreach ($event->getCommandsForEntity(DocumentBaseConfigDefinition::ENTITY_NAME) as $command) {
            $this->validatePaymentDueDate($command, $violations);
            $this->validateFilenameInfixes($command, $violations);
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(
                new WriteConstraintViolationException($violations),
            );
        }
    }

    private function validatePaymentDueDate(WriteCommand $command, ConstraintViolationList $violations): void
    {
        if (!$command->hasField('config')) {
            return;
        }

        $encodedConfig = $command->getPayload()['config'];
        if (!\is_string($encodedConfig)) {
            return;
        }

        $config = json_decode($encodedConfig, true, 512, \JSON_THROW_ON_ERROR);
        $paymentDueDate = $config['paymentDueDate'] ?? null;

        if ($paymentDueDate === null || $paymentDueDate === '') {
            return;
        }

        if (\is_string($paymentDueDate) && $this->isValidDateModifier($paymentDueDate)) {
            return;
        }

        $messageTemplate = 'The payment due date must be a relative date such as "{{ example }}".';
        $parameters = ['{{ example }}' => '+14 days'];

        $violations->add(new ConstraintViolation(
            message: str_replace(\array_keys($parameters), \array_values($parameters), $messageTemplate),
            messageTemplate: $messageTemplate,
            parameters: $parameters,
            root: null,
            propertyPath: $command->getPath() . '/config/paymentDueDate',
            invalidValue: $paymentDueDate,
            code: self::INVALID_PAYMENT_DUE_DATE,
        ));
    }

    /**
     * Formats of one document type that share a file extension need distinct infixes, or they render to the same
     * filename. A sales-channel config is checked with the global infixes merged in, a global config against the
     * sales-channel configs that inherit from it.
     */
    private function validateFilenameInfixes(WriteCommand $command, ConstraintViolationList $violations): void
    {
        $writesInfixes = $command->hasField('filename_infixes');
        if (!$writesInfixes && !$this->changesScope($command)) {
            return;
        }

        $scope = $this->resolveScope($command, needsStoredInfixes: !$writesInfixes);
        if ($scope === null) {
            return;
        }

        ['typeName' => $typeName, 'global' => $global, 'storedInfixes' => $storedInfixes] = $scope;
        $infixes = $this->decodeInfixes($writesInfixes ? $command->getPayload()['filename_infixes'] : $storedInfixes);

        $rendererRegistry = ($this->documentRendererRegistry)();
        $extensionByFormat = [];
        foreach ($this->documentTypeRegistry->getSupportedFormats($typeName) as $format) {
            $extension = $rendererRegistry->getFileExtension($format);
            if ($extension !== null) {
                $extensionByFormat[$format] = $extension;
            }
        }

        if (\count(array_unique($extensionByFormat)) === \count($extensionByFormat)) {
            return;
        }

        if (!$global) {
            $infixes = DocumentConfigLoader::mergeFilenameInfixes($this->fetchGlobalInfixes($typeName, $command), $infixes);
        }

        $candidates = [['infixes' => $infixes, 'overridden' => [], 'salesChannelConfig' => null]];
        if ($global) {
            foreach ($this->fetchAssignedSalesChannelConfigs($typeName, $command) as $salesChannelConfig) {
                $overrides = $this->decodeInfixes($salesChannelConfig['filename_infixes']);
                $candidates[] = [
                    'infixes' => DocumentConfigLoader::mergeFilenameInfixes($infixes, $overrides),
                    'overridden' => array_keys($overrides),
                    'salesChannelConfig' => (string) $salesChannelConfig['name'],
                ];
            }
        }

        $clashes = [];
        foreach ($candidates as ['infixes' => $effectiveInfixes, 'overridden' => $overriddenFormats, 'salesChannelConfig' => $salesChannelConfigName]) {
            $formatsByFilename = [];
            foreach ($extensionByFormat as $format => $extension) {
                $formatsByFilename[mb_strtolower(($effectiveInfixes[$format] ?? '') . '.' . $extension)][] = $format;
            }

            foreach ($formatsByFilename as $clashingFormats) {
                if (\count($clashingFormats) < 2) {
                    continue;
                }

                foreach (array_diff($clashingFormats, $overriddenFormats) as $format) {
                    $clash = $clashes[$format] ?? ['formats' => [], 'configs' => []];
                    $clash['formats'] = array_values(array_unique([...$clash['formats'], ...array_diff($clashingFormats, [$format])]));
                    if ($salesChannelConfigName !== null) {
                        $clash['configs'] = array_values(array_unique([...$clash['configs'], $salesChannelConfigName]));
                    }
                    $clashes[$format] = $clash;
                }
            }
        }

        foreach ($clashes as $format => $clash) {
            $parameters = [
                '{{ infix }}' => $infixes[$format] ?? '',
                '{{ format }}' => $format,
                '{{ formats }}' => implode(', ', $clash['formats']),
                '{{ extension }}' => $extensionByFormat[$format],
            ];
            $messageTemplate = 'The filename infix "{{ infix }}" for "{{ format }}" produces the same ".{{ extension }}" filename as: {{ formats }}';
            if ($clash['configs'] !== []) {
                $parameters['{{ configs }}'] = implode(', ', $clash['configs']);
                $messageTemplate .= ' in the sales channel configuration: {{ configs }}';
            }

            $violations->add(new ConstraintViolation(
                message: str_replace(\array_keys($parameters), \array_values($parameters), $messageTemplate . '.'),
                messageTemplate: $messageTemplate . '.',
                parameters: $parameters,
                root: null,
                propertyPath: $command->getPath() . '/filenameInfixes/' . $format,
                invalidValue: $parameters['{{ infix }}'],
                code: self::DUPLICATE_FILENAME_INFIX,
            ));
        }
    }

    private function changesScope(WriteCommand $command): bool
    {
        if (!$command instanceof UpdateCommand) {
            return false;
        }

        foreach (self::SCOPE_FIELDS as $field) {
            if ($command->hasField($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{typeName: string, global: bool, storedInfixes: string|null}|null
     */
    private function resolveScope(WriteCommand $command, bool $needsStoredInfixes): ?array
    {
        $payload = $command->getPayload();
        $typeName = $command->hasField('type_name') ? $payload['type_name'] : null;
        $global = $command->hasField('global') ? $payload['global'] : null;
        $storedInfixes = null;

        if ($command instanceof UpdateCommand && ($typeName === null || $global === null || $needsStoredInfixes)) {
            $existing = $this->connection->fetchAssociative(
                'SELECT `type_name`, `global`, `filename_infixes` FROM `document_base_config` WHERE `id` = :id',
                ['id' => $command->getPrimaryKey()['id']],
            );

            if (\is_array($existing)) {
                $typeName ??= $existing['type_name'];
                $global ??= $existing['global'];
                $storedInfixes = $existing['filename_infixes'];
            }
        }

        if (!\is_string($typeName) || $global === null) {
            return null;
        }

        return ['typeName' => $typeName, 'global' => (bool) $global, 'storedInfixes' => \is_string($storedInfixes) ? $storedInfixes : null];
    }

    /**
     * @return array<string, string>
     */
    private function fetchGlobalInfixes(string $typeName, WriteCommand $command): array
    {
        return $this->decodeInfixes($this->connection->fetchOne(
            'SELECT `filename_infixes` FROM `document_base_config` WHERE `type_name` = :typeName AND `global` = 1 AND `id` != :id LIMIT 1',
            ['typeName' => $typeName, 'id' => $command->getPrimaryKey()['id']],
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAssignedSalesChannelConfigs(string $typeName, WriteCommand $command): array
    {
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT `config`.`name`, `config`.`filename_infixes`
                FROM `document_base_config` AS `config`
                WHERE `config`.`type_name` = :typeName
                  AND `config`.`global` = 0
                  AND `config`.`id` != :id
                  AND EXISTS (
                      SELECT 1
                      FROM `document_base_config_sales_channel` AS `assignment`
                      WHERE `assignment`.`document_base_config_id` = `config`.`id`
                  )
                ORDER BY `config`.`name`
                SQL,
            ['typeName' => $typeName, 'id' => $command->getPrimaryKey()['id']],
        );
    }

    /**
     * @return array<string, string>
     */
    private function decodeInfixes(mixed $encodedInfixes): array
    {
        if (!\is_string($encodedInfixes)) {
            return [];
        }

        $infixes = json_decode($encodedInfixes, true, 512, \JSON_THROW_ON_ERROR);

        return DocumentConfigLoader::configuredFilenameInfixes(\is_array($infixes) ? $infixes : []);
    }

    private function isValidDateModifier(string $value): bool
    {
        try {
            $this->clock->now()->modify($value);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
