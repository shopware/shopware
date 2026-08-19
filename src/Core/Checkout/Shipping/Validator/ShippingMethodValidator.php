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

    /**
     * An active shipping method without any price can never resolve shipping costs: the cart blocks it while
     * the storefront still offers it. Rejects removing the last price and activating a priceless method;
     * creation stays allowed. Prices whose rules never match fail alike, but depend on the cart.
     */
    private function validateActiveMethodsHavePrices(PreWriteValidationEvent $event): void
    {
        [$deletedMethodIds, $activeOverrides] = $this->collectShippingMethodChanges($event);
        $priceCountChanges = $this->getPriceCountChanges($event);

        $candidates = array_filter($activeOverrides)
            + array_filter($priceCountChanges, static fn (int $change): bool => $change < 0);

        // A shipping method that is removed in the same write cascades its prices away with it
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
                    'The shipping method {{ id }} is active and must therefore have at least one price.',
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
     * Resolves the final owner of every touched price before calculating per-method count changes.
     * This also covers moving an existing price and multiple commands for the same price in one sync operation.
     *
     * @return array<string, int>
     */
    private function getPriceCountChanges(PreWriteValidationEvent $event): array
    {
        $commands = array_values(array_filter(
            $event->getCommandsForEntity(ShippingMethodPriceDefinition::ENTITY_NAME),
            $this->changesPriceOwner(...)
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

        $currentOwners = $this->fetchPriceOwners(array_keys($priceIds));
        $finalOwners = $currentOwners;

        foreach ($commands as $command) {
            $this->applyPriceCommand($finalOwners, $command);
        }

        $changes = array_map(static fn (int $count): int => -$count, array_count_values($currentOwners));
        foreach (array_count_values($finalOwners) as $shippingMethodId => $count) {
            $changes[$shippingMethodId] = ($changes[$shippingMethodId] ?? 0) + $count;
        }

        return array_filter($changes);
    }

    private function changesPriceOwner(WriteCommand $command): bool
    {
        return $command instanceof DeleteCommand
            || $command instanceof InsertCommand
            || ($command instanceof UpdateCommand && $command->hasAnyField('shipping_method_id'));
    }

    /**
     * @param array<string, string> $priceOwners
     */
    private function applyPriceCommand(array &$priceOwners, WriteCommand $command): void
    {
        $id = $this->readIdFromPrimaryKey($command);
        if ($id === null) {
            return;
        }

        if ($command instanceof DeleteCommand) {
            unset($priceOwners[$id]);

            return;
        }

        // An update after a delete does not recreate the row
        if ($command instanceof UpdateCommand && !isset($priceOwners[$id])) {
            return;
        }

        $shippingMethodId = $command->getPayload()['shipping_method_id'] ?? null;
        if (\is_string($shippingMethodId)) {
            $priceOwners[$id] = Uuid::fromBytesToHex($shippingMethodId);

            return;
        }

        unset($priceOwners[$id]);
    }

    private function readIdFromPrimaryKey(WriteCommand $command): ?string
    {
        $id = $command->getDecodedPrimaryKey()['id'] ?? null;

        return \is_string($id) ? $id : null;
    }

    /**
     * @param list<string> $priceIds
     *
     * @return array<string, string>
     */
    private function fetchPriceOwners(array $priceIds): array
    {
        if ($priceIds === []) {
            return [];
        }

        sort($priceIds);

        $rows = $this->connection->fetchAllNumeric(
            'SELECT LOWER(HEX(`id`)), LOWER(HEX(`shipping_method_id`))
             FROM `shipping_method_price`
             WHERE `id` IN (:ids)
             ORDER BY `id`
             FOR UPDATE',
            ['ids' => Uuid::fromHexToBytesList($priceIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $owners = [];
        foreach ($rows as $row) {
            $priceId = $row[0] ?? null;
            $shippingMethodId = $row[1] ?? null;
            if (!\is_string($priceId) || !\is_string($shippingMethodId)) {
                continue;
            }

            $owners[$priceId] = $shippingMethodId;
        }

        return $owners;
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

        // Serialize checks for the same method, so concurrent deletions cannot both validate stale price counts
        $this->connection->fetchFirstColumn(
            'SELECT `id` FROM `shipping_method` WHERE `id` IN (:ids) ORDER BY `id` FOR UPDATE',
            $parameters,
            $types
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`shipping_method`.`id`)) AS `id`,
                    `shipping_method`.`active` AS `active`,
                    COUNT(`shipping_method_price`.`id`) AS `price_count`
             FROM `shipping_method`
             LEFT JOIN `shipping_method_price`
                 ON `shipping_method_price`.`shipping_method_id` = `shipping_method`.`id`
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
