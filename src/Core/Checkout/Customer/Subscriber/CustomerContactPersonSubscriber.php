<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
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
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerContactPersonSubscriber implements EventSubscriberInterface
{
    final public const MESSAGE = 'A contact person or a company is required.';

    private const NAME_FIELDS = ['first_name', 'last_name', 'company', 'account_type'];

    public function __construct(private readonly CustomerContactPersonRowReader $rowReader)
    {
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

        $this->validateEntity($event, CustomerDefinition::ENTITY_NAME, $violations, true);
        $this->validateEntity($event, CustomerAddressDefinition::ENTITY_NAME, $violations, false);
        $this->validateEntity($event, OrderCustomerDefinition::ENTITY_NAME, $violations, false);
        $this->validateEntity($event, OrderAddressDefinition::ENTITY_NAME, $violations, false);

        if ($violations->count() === 0) {
            return;
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    private function validateEntity(PreWriteValidationEvent $event, string $entity, ConstraintViolationList $violations, bool $hasAccountType): void
    {
        $commands = $this->collectCommands($event, $entity);

        if ($commands === []) {
            return;
        }

        $stored = $this->fetchStored($entity, $commands, $hasAccountType);

        foreach ($commands as $id => ['command' => $command, 'payload' => $payload]) {
            $row = $stored[$id] ?? [];

            $firstName = trim((string) ($payload['first_name'] ?? $row['first_name'] ?? ''));
            $lastName = trim((string) ($payload['last_name'] ?? $row['last_name'] ?? ''));

            if ($firstName !== '' || $lastName !== '') {
                continue;
            }

            $company = \array_key_exists('company', $payload) ? $payload['company'] : ($row['company'] ?? null);
            $accountType = $payload['account_type'] ?? $row['account_type'] ?? CustomerEntity::ACCOUNT_TYPE_PRIVATE;

            if ((!$hasAccountType || $accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS) && trim((string) $company) !== '') {
                continue;
            }

            $violations->add(new ConstraintViolation(
                self::MESSAGE,
                self::MESSAGE,
                [],
                null,
                $command->getPath() . '/firstName',
                $firstName,
                null,
                NotBlank::IS_BLANK_ERROR
            ));
        }
    }

    /**
     * @return array<string, array{command: InsertCommand|UpdateCommand, payload: array<string, mixed>}>
     */
    private function collectCommands(PreWriteValidationEvent $event, string $entity): array
    {
        $commands = [];

        foreach ($event->getCommandsForEntity($entity) as $command) {
            if (!$command instanceof InsertCommand && !($command instanceof UpdateCommand && $command->hasAnyField(...self::NAME_FIELDS))) {
                continue;
            }

            $key = self::commandKey($command);

            if (isset($commands[$key])) {
                $commands[$key]['payload'] = array_merge($commands[$key]['payload'], $command->getPayload());

                continue;
            }

            $commands[$key] = ['command' => $command, 'payload' => $command->getPayload()];
        }

        return $commands;
    }

    private static function commandKey(WriteCommand $command): string
    {
        $id = strtolower($command->getDecodedPrimaryKey()['id']);
        $versionId = $command->getPrimaryKey()['version_id'] ?? null;

        return $versionId === null ? $id : $id . '-' . Uuid::fromBytesToHex($versionId);
    }

    /**
     * @param array<string, string|null> $row
     */
    private static function rowKey(array $row): string
    {
        $id = strtolower((string) $row['id']);
        $versionId = $row['version_id'] ?? null;

        return $versionId === null ? $id : $id . '-' . strtolower($versionId);
    }

    /**
     * @param array<string, array{command: InsertCommand|UpdateCommand, payload: array<string, mixed>}> $commands
     *
     * @return array<string, array<string, string|null>>
     */
    private function fetchStored(string $entity, array $commands, bool $hasAccountType): array
    {
        $ids = [];
        $versioned = false;
        foreach ($commands as ['command' => $command]) {
            if ($command instanceof UpdateCommand) {
                $ids[] = $command->getDecodedPrimaryKey()['id'];
                $versioned = $versioned || isset($command->getPrimaryKey()['version_id']);
            }
        }

        if ($ids === []) {
            return [];
        }

        $rows = $this->rowReader->read($entity, array_values(array_unique($ids)), $hasAccountType, $versioned);

        $stored = [];
        foreach ($rows as $row) {
            $stored[self::rowKey($row)] = $row;
        }

        return $stored;
    }
}
