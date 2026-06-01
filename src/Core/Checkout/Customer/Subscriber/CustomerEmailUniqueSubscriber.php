<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueCheck;
use Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber\CustomerEmailUniqueSubscriberTest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @codeCoverageIgnore Tested via integration tests.
 *
 * @see CustomerEmailUniqueSubscriberTest
 */
#[Package('checkout')]
class CustomerEmailUniqueSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CustomerEmailUniqueChecker $customerEmailUniqueChecker,
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
        $customerCommands = $this->collectCustomerCommands($event);
        if ($customerCommands === []) {
            return;
        }

        $relevantCustomerIds = $this->getRelevantCustomerIds($customerCommands);
        // Avoid loading current customer state unless the write can affect email uniqueness.
        if ($relevantCustomerIds === []) {
            return;
        }

        $customerStates = $this->resolveCustomerStates($customerCommands);
        $validatedCustomerStates = \array_intersect_key($customerStates, \array_flip($relevantCustomerIds));
        if ($validatedCustomerStates === []) {
            return;
        }

        $checks = [];
        foreach ($validatedCustomerStates as $customerId => $state) {
            $checks[] = new CustomerEmailUniqueCheck(
                email: $state['email'],
                customerId: $customerId,
                boundSalesChannelId: $state['boundSalesChannelId'],
                guest: $state['guest'],
            );
        }

        $conflictingChecks = $this->customerEmailUniqueChecker->findConflictingChecks(...$checks);
        if ($conflictingChecks === []) {
            return;
        }

        $violations = new ConstraintViolationList();

        foreach ($conflictingChecks as $check) {
            \assert($check->customerId !== null);

            $this->addViolation($violations, $check->customerId, $check->email);
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @return array<string, WriteCommand>
     */
    private function collectCustomerCommands(PreWriteValidationEvent $event): array
    {
        $commands = [];

        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== CustomerDefinition::ENTITY_NAME) {
                continue;
            }

            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $commands[Uuid::fromBytesToHex($command->getPrimaryKey()['id'])] = $command;
        }

        return $commands;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return list<string>
     */
    private function getRelevantCustomerIds(array $commands): array
    {
        $customerIds = [];

        foreach ($commands as $customerId => $command) {
            if ($command instanceof InsertCommand) {
                $customerIds[] = $customerId;

                continue;
            }

            $payload = $command->getPayload();
            if (\array_key_exists('email', $payload) || \array_key_exists('guest', $payload) || \array_key_exists('bound_sales_channel_id', $payload)) {
                $customerIds[] = $customerId;
            }
        }

        return $customerIds;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return array<string, array{email: string, guest: bool, boundSalesChannelId: string|null}>
     */
    private function resolveCustomerStates(array $commands): array
    {
        $currentStates = $this->fetchCurrentCustomerStates($commands);
        $states = [];

        foreach ($commands as $customerId => $command) {
            $payload = $command->getPayload();
            $currentState = $currentStates[$customerId] ?? null;

            $email = $payload['email'] ?? $currentState['email'] ?? null;
            if (!\is_string($email)) {
                continue;
            }

            $states[$customerId] = [
                'email' => $email,
                'guest' => \array_key_exists('guest', $payload) ? (bool) $payload['guest'] : ($currentState['guest'] ?? false),
                'boundSalesChannelId' => \array_key_exists('bound_sales_channel_id', $payload)
                    ? $this->normalizeBoundSalesChannelId($payload['bound_sales_channel_id'])
                    : ($currentState['boundSalesChannelId'] ?? null),
            ];
        }

        return $states;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return array<string, array{email: string, guest: bool, boundSalesChannelId: string|null}>
     */
    private function fetchCurrentCustomerStates(array $commands): array
    {
        $customerIds = [];
        foreach ($commands as $customerId => $command) {
            if ($command instanceof UpdateCommand) {
                $customerIds[] = $customerId;
            }
        }

        if ($customerIds === []) {
            return [];
        }

        /** @var list<array{id: string, email: string, guest: int, bound_sales_channel_id: string|null}> $customers */
        $customers = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) as `id`, `email`, `guest`, LOWER(HEX(`bound_sales_channel_id`)) as `bound_sales_channel_id`
             FROM `customer`
             WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($customerIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $states = [];
        foreach ($customers as $customer) {
            $states[$customer['id']] = [
                'email' => $customer['email'],
                'guest' => (bool) $customer['guest'],
                'boundSalesChannelId' => $customer['bound_sales_channel_id'],
            ];
        }

        return $states;
    }

    private function addViolation(ConstraintViolationList $violations, string $customerId, string $email): void
    {
        $message = 'The email address {{ email }} is already in use.';

        $violations->add(new ConstraintViolation(
            str_replace('{{ email }}', $email, $message),
            $message,
            ['{{ email }}' => $email],
            null,
            '/' . $customerId . '/email',
            $email,
            null,
            CustomerEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE,
        ));
    }

    private function normalizeBoundSalesChannelId(mixed $boundSalesChannelId): ?string
    {
        if ($boundSalesChannelId === null) {
            return null;
        }

        if (!\is_string($boundSalesChannelId)) {
            return null;
        }

        if (Uuid::isValid($boundSalesChannelId)) {
            return $boundSalesChannelId;
        }

        return Uuid::fromBytesToHex($boundSalesChannelId);
    }
}
