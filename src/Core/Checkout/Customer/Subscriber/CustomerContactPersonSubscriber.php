<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
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

    final public const NAME_MESSAGE = 'This value should not be blank.';

    private const NAME_COLUMNS = ['first_name', 'last_name', 'company'];

    public function __construct(
        private readonly WriteOutcome $writeOutcome,
        private readonly RowLoader $rowLoader,
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

        $accountTypes = $this->validateCustomers($event, $violations);
        $this->validateCustomerAddresses($event, $violations, $accountTypes);
        $this->validateEntity($event, OrderCustomerDefinition::ENTITY_NAME, $violations);
        $this->validateEntity($event, OrderAddressDefinition::ENTITY_NAME, $violations);

        if ($violations->count() === 0) {
            return;
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @return array<string, mixed> the account type of each customer this write touches, keyed by hex id
     */
    private function validateCustomers(PreWriteValidationEvent $event, ConstraintViolationList $violations): array
    {
        $rows = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(CustomerDefinition::ENTITY_NAME),
            [...self::NAME_COLUMNS, 'account_type'],
        );

        $accountTypes = [];
        foreach ($rows as $row) {
            $accountType = $row->getResultingValue('account_type');
            $accountTypes[Uuid::fromBytesToHex($row->primaryKey['id'])] = $accountType;

            $this->requireNamesFor($accountType, $row, $violations);
        }

        return $accountTypes;
    }

    /**
     * @param array<string, mixed> $accountTypes
     */
    private function validateCustomerAddresses(PreWriteValidationEvent $event, ConstraintViolationList $violations, array $accountTypes): void
    {
        $rows = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(CustomerAddressDefinition::ENTITY_NAME),
            [...self::NAME_COLUMNS, 'customer_id'],
        );

        if ($rows === []) {
            return;
        }

        $customerIds = [];
        foreach ($rows as $row) {
            $customerId = $row->getResultingValue('customer_id');
            if (\is_string($customerId)) {
                $customerIds[] = Uuid::fromBytesToHex($customerId);
            }
        }

        $accountTypes += $this->loadAccountTypes(array_values(array_diff(array_unique($customerIds), array_keys($accountTypes))));

        foreach ($rows as $row) {
            $customerId = $row->getResultingValue('customer_id');
            $accountType = \is_string($customerId) ? ($accountTypes[Uuid::fromBytesToHex($customerId)] ?? null) : null;

            $this->requireNamesFor($accountType, $row, $violations);
        }
    }

    /**
     * @param list<string> $customerIds
     *
     * @return array<string, mixed>
     */
    private function loadAccountTypes(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $primaryKeys = array_map(static fn (string $id): array => ['id' => Uuid::fromHexToBytes($id)], $customerIds);

        $accountTypes = [];
        foreach ($this->rowLoader->load(CustomerDefinition::ENTITY_NAME, $primaryKeys, ['id', 'account_type']) as $customer) {
            $accountTypes[Uuid::fromBytesToHex((string) $customer['id'])] = $customer['account_type'];
        }

        return $accountTypes;
    }

    private function requireNamesFor(mixed $accountType, OutcomeRow $row, ConstraintViolationList $violations): void
    {
        if ($accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            $this->requireNameOrCompany($row, $violations);

            return;
        }

        foreach (['first_name' => 'firstName', 'last_name' => 'lastName'] as $column => $field) {
            if ($this->value($row, $column) === '') {
                $violations->add($this->violation($row, $field, self::NAME_MESSAGE));
            }
        }
    }

    private function validateEntity(PreWriteValidationEvent $event, string $entity, ConstraintViolationList $violations): void
    {
        foreach ($this->writeOutcome->resultingRows($event->getCommandsForEntity($entity), self::NAME_COLUMNS) as $row) {
            $this->requireNameOrCompany($row, $violations);
        }
    }

    private function requireNameOrCompany(OutcomeRow $row, ConstraintViolationList $violations): void
    {
        if ($this->value($row, 'first_name') !== '' || $this->value($row, 'last_name') !== '' || $this->value($row, 'company') !== '') {
            return;
        }

        $violations->add($this->violation($row, 'firstName', self::MESSAGE));
    }

    private function value(OutcomeRow $row, string $column): string
    {
        $value = $row->getResultingValue($column);

        return \is_string($value) ? trim($value) : '';
    }

    private function violation(OutcomeRow $row, string $field, string $message): ConstraintViolation
    {
        return new ConstraintViolation(
            $message,
            $message,
            [],
            null,
            $row->path . '/' . $field,
            $row->getResultingValue($field === 'firstName' ? 'first_name' : 'last_name'),
            null,
            NotBlank::IS_BLANK_ERROR
        );
    }
}
