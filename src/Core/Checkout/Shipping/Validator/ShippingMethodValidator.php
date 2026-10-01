<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Validator;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
class ShippingMethodValidator implements EventSubscriberInterface
{
    final public const VIOLATION_TAX_TYPE_INVALID = 'tax_type_invalid';

    final public const VIOLATION_TAX_ID_REQUIRED = NotBlank::IS_BLANK_ERROR;

    final public const VIOLATION_ACTIVE_WITHOUT_PRICE = 'active_shipping_method_without_price';

    private const ALLOWED_TAX_TYPES = [
        ShippingMethodEntity::TAX_TYPE_FIXED,
        ShippingMethodEntity::TAX_TYPE_AUTO,
        ShippingMethodEntity::TAX_TYPE_HIGHEST,
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
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommandsForEntity(ShippingMethodDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $shippingMethod = $this->findShippingMethod($command->getPrimaryKey()['id']);
            $payload = $command->getPayload();

            $taxType = $payload['tax_type'] ?? $shippingMethod['tax_type'] ?? null;
            \assert($taxType === null || \is_string($taxType));

            $taxId = $payload['tax_id'] ?? $shippingMethod['tax_id'] ?? null;
            \assert($taxId === null || \is_string($taxId));

            $violations = new ConstraintViolationList();
            if ($taxType && !\in_array($taxType, self::ALLOWED_TAX_TYPES, true)) {
                $violations->add(
                    $this->buildViolation(
                        'The selected tax type {{ type }} is invalid.',
                        ['{{ type }}' => $taxType],
                        '/taxType',
                        $taxType,
                        self::VIOLATION_TAX_TYPE_INVALID
                    )
                );
            }

            // Use `Uuid::fromBytesToHex` to validate the binary encoded `taxId`
            if ($taxType === ShippingMethodEntity::TAX_TYPE_FIXED && ($taxId === null || !Uuid::fromBytesToHex($taxId))) {
                $violations->add(
                    $this->buildViolation(
                        'The defined tax rate is required when fixed tax present',
                        ['{{ taxId }}' => null],
                        '/taxId',
                        $taxType,
                        self::VIOLATION_TAX_ID_REQUIRED
                    )
                );
            }

            if ($violations->count() > 0) {
                $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
            }
        }

        $this->validateActiveMethodsHavePrices($event);
    }

    private function validateActiveMethodsHavePrices(PreWriteValidationEvent $event): void
    {
        [$deletedMethodIds, $activeOverrides] = $this->collectShippingMethodChanges($event);
        $priceCountChanges = $this->getPriceCountChanges($event);

        $candidates = array_filter($activeOverrides)
            + array_filter($priceCountChanges, static fn (int $change): bool => $change < 0);

        // A method deleted in the same write takes its prices with it
        $candidates = array_diff_key($candidates, $deletedMethodIds);
        if ($candidates === []) {
            return;
        }

        $states = $this->fetchShippingMethodStates(array_keys($candidates));

        $violations = new ConstraintViolationList();

        foreach (array_keys($candidates) as $id) {
            $active = $activeOverrides[$id] ?? $states[$id]['active'] ?? ShippingMethodEntity::ACTIVE_DEFAULT;
            if (!$active) {
                continue;
            }

            $priceCount = ($states[$id]['priceCount'] ?? 0) + ($priceCountChanges[$id] ?? 0);

            if ($priceCount > 0) {
                continue;
            }

            $violations->add(
                $this->buildViolation(
                    'The shipping method {{ id }} is active and must therefore have at least one price with currency values.',
                    ['{{ id }}' => $id],
                    '/prices',
                    $id,
                    self::VIOLATION_ACTIVE_WITHOUT_PRICE
                )
            );
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @return array{array<string, true>, array<string, bool>}
     */
    private function collectShippingMethodChanges(PreWriteValidationEvent $event): array
    {
        $deletedMethodIds = [];
        $activeOverrides = [];

        foreach ($event->getCommandsForEntity(ShippingMethodDefinition::ENTITY_NAME) as $command) {
            $id = $this->readIdFromPrimaryKey($command);
            if ($id === null) {
                continue;
            }

            if ($command instanceof DeleteCommand) {
                $deletedMethodIds[$id] = true;

                continue;
            }

            if (!$command instanceof UpdateCommand) {
                continue;
            }

            $active = $command->getPayload()['active'] ?? null;
            if ($active !== null) {
                $activeOverrides[$id] = (bool) $active;
            }
        }

        return [$deletedMethodIds, $activeOverrides];
    }

    /**
     * @return array<string, int>
     */
    private function getPriceCountChanges(PreWriteValidationEvent $event): array
    {
        $commands = array_values(array_filter(
            $event->getCommandsForEntity(ShippingMethodPriceDefinition::ENTITY_NAME),
            $this->changesPriceAvailability(...)
        ));
        $priceIds = [];

        foreach ($commands as $command) {
            $id = $this->readIdFromPrimaryKey($command);
            if ($id !== null) {
                $priceIds[$id] = true;
            }
        }

        if ($priceIds === []) {
            return [];
        }

        $currentStates = $this->fetchPriceStates(array_keys($priceIds));
        $finalStates = $currentStates;

        foreach ($commands as $command) {
            $this->applyPriceCommand($finalStates, $command);
        }

        $changes = array_map(static fn (int $count): int => -$count, array_count_values($this->ownersOfUsablePrices($currentStates)));
        foreach (array_count_values($this->ownersOfUsablePrices($finalStates)) as $shippingMethodId => $count) {
            $changes[$shippingMethodId] = ($changes[$shippingMethodId] ?? 0) + $count;
        }

        return array_filter($changes);
    }

    private function changesPriceAvailability(WriteCommand $command): bool
    {
        return $command instanceof DeleteCommand
            || $command instanceof InsertCommand
            || ($command instanceof UpdateCommand && $command->hasAnyField('shipping_method_id', 'currency_price'));
    }

    /**
     * @param array<string, array{owner: string, usable: bool}> $priceStates
     */
    private function applyPriceCommand(array &$priceStates, WriteCommand $command): void
    {
        $id = $this->readIdFromPrimaryKey($command);
        if ($id === null) {
            return;
        }

        if ($command instanceof DeleteCommand) {
            unset($priceStates[$id]);

            return;
        }

        // An update after a delete must not recreate the row
        if ($command instanceof UpdateCommand && !isset($priceStates[$id])) {
            return;
        }

        $payload = $command->getPayload();

        $owner = $priceStates[$id]['owner'] ?? null;
        if (\array_key_exists('shipping_method_id', $payload)) {
            $shippingMethodId = $payload['shipping_method_id'];
            $owner = \is_string($shippingMethodId) ? Uuid::fromBytesToHex($shippingMethodId) : null;
        }

        if ($owner === null) {
            unset($priceStates[$id]);

            return;
        }

        $usable = $priceStates[$id]['usable'] ?? false;
        if (\array_key_exists('currency_price', $payload)) {
            $usable = $payload['currency_price'] !== null;
        }

        $priceStates[$id] = ['owner' => $owner, 'usable' => $usable];
    }

    /**
     * @param array<string, array{owner: string, usable: bool}> $priceStates
     *
     * @return list<string>
     */
    private function ownersOfUsablePrices(array $priceStates): array
    {
        $owners = [];
        foreach ($priceStates as $state) {
            if ($state['usable']) {
                $owners[] = $state['owner'];
            }
        }

        return $owners;
    }

    private function readIdFromPrimaryKey(WriteCommand $command): ?string
    {
        $id = $command->getDecodedPrimaryKey()['id'] ?? null;

        return \is_string($id) ? $id : null;
    }

    /**
     * @param list<string> $priceIds
     *
     * @return array<string, array{owner: string, usable: bool}>
     */
    private function fetchPriceStates(array $priceIds): array
    {
        sort($priceIds);

        $rows = $this->connection->fetchAllNumeric(
            'SELECT LOWER(HEX(`id`)), LOWER(HEX(`shipping_method_id`)), `currency_price` IS NOT NULL
             FROM `shipping_method_price`
             WHERE `id` IN (:ids)
             ORDER BY `id`
             FOR UPDATE',
            ['ids' => Uuid::fromHexToBytesList($priceIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $states = [];
        foreach ($rows as $row) {
            $priceId = $row[0] ?? null;
            $shippingMethodId = $row[1] ?? null;
            if (!\is_string($priceId) || !\is_string($shippingMethodId)) {
                continue;
            }

            $states[$priceId] = ['owner' => $shippingMethodId, 'usable' => (bool) ($row[2] ?? false)];
        }

        return $states;
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $types
     */
    private function lockShippingMethods(array $parameters, array $types): void
    {
        $this->connection->fetchFirstColumn(
            'SELECT `id` FROM `shipping_method` WHERE `id` IN (:ids) ORDER BY `id` FOR UPDATE',
            $parameters,
            $types
        );
    }

    /**
     * @param list<string> $shippingMethodIds
     *
     * @return array<string, array{active: bool, priceCount: int}>
     */
    private function fetchShippingMethodStates(array $shippingMethodIds): array
    {
        sort($shippingMethodIds);
        $parameters = ['ids' => Uuid::fromHexToBytesList($shippingMethodIds)];
        $types = ['ids' => ArrayParameterType::BINARY];

        $this->lockShippingMethods($parameters, $types);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`shipping_method`.`id`)) AS `id`,
                    `shipping_method`.`active` AS `active`,
                    COUNT(`shipping_method_price`.`id`) AS `price_count`
             FROM `shipping_method`
             LEFT JOIN `shipping_method_price`
                 ON `shipping_method_price`.`shipping_method_id` = `shipping_method`.`id`
                 AND `shipping_method_price`.`currency_price` IS NOT NULL
             WHERE `shipping_method`.`id` IN (:ids)
             GROUP BY `shipping_method`.`id`, `shipping_method`.`active`
             FOR UPDATE',
            $parameters,
            $types
        );

        $states = [];
        foreach ($rows as $row) {
            $states[(string) $row['id']] = [
                'active' => (bool) $row['active'],
                'priceCount' => (int) $row['price_count'],
            ];
        }

        return $states;
    }

    /**
     * @return array<string, mixed>
     */
    private function findShippingMethod(string $shippingMethodId): array
    {
        return $this->connection->executeQuery(
            'SELECT `tax_type`, `tax_id` FROM `shipping_method` WHERE `id` = :id',
            ['id' => $shippingMethodId]
        )->fetchAssociative() ?: [];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        string $propertyPath,
        string $invalidValue,
        string $code
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            null,
            $propertyPath,
            $invalidValue,
            null,
            $code
        );
    }
}
