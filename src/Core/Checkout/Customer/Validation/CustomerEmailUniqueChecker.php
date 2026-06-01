<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber\CustomerEmailUniqueSubscriberTest;

/**
 * @final
 *
 * @codeCoverageIgnore Tested via integration tests.
 *
 * @see CustomerEmailUniqueSubscriberTest
 */
#[Package('checkout')]
class CustomerEmailUniqueChecker
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function isUnique(CustomerEmailUniqueCheck $check): bool
    {
        return $this->findConflictingCustomerId($check) === null;
    }

    public function findConflictingCustomerId(CustomerEmailUniqueCheck $check): ?string
    {
        if ($check->guest) {
            return null;
        }

        $isCustomerBoundToSalesChannel = $this->isCustomerBoundToSalesChannel();

        foreach ($this->fetchExistingCustomers([$check->email]) as $customer) {
            if ($customer['id'] === $check->customerId) {
                continue;
            }

            if (!$this->isSameEmail($check->email, $customer['email'])) {
                continue;
            }

            if (!$this->isInSameEmailUniquenessScope($check->boundSalesChannelId, $customer['boundSalesChannelId'], $isCustomerBoundToSalesChannel)) {
                continue;
            }

            return $customer['id'];
        }

        return null;
    }

    /**
     * Returns the submitted checks that conflict with another submitted check
     * or with an existing non-guest customer.
     *
     * @return list<CustomerEmailUniqueCheck>
     */
    public function findConflictingChecks(CustomerEmailUniqueCheck ...$checks): array
    {
        $checks = \array_values(\array_filter($checks, static fn (CustomerEmailUniqueCheck $check): bool => !$check->guest));
        if ($checks === []) {
            return [];
        }

        $conflictingChecks = [];
        $conflictingIndexes = [];
        $candidateCustomerIds = [];
        $isCustomerBoundToSalesChannel = $this->isCustomerBoundToSalesChannel();

        foreach ($checks as $check) {
            if ($check->customerId !== null) {
                $candidateCustomerIds[$check->customerId] = true;
            }
        }

        foreach ($checks as $index => $check) {
            foreach ($checks as $comparedIndex => $comparedCheck) {
                if ($index === $comparedIndex || $this->isSameCustomer($check, $comparedCheck)) {
                    continue;
                }

                if (!$this->isSameEmail($check->email, $comparedCheck->email)) {
                    continue;
                }

                if (!$this->isInSameEmailUniquenessScope($check->boundSalesChannelId, $comparedCheck->boundSalesChannelId, $isCustomerBoundToSalesChannel)) {
                    continue;
                }

                $this->addConflictingCheck($conflictingChecks, $conflictingIndexes, $index, $check);

                break;
            }
        }

        $existingCustomers = $this->fetchExistingCustomers(\array_values(\array_unique(\array_map(
            static fn (CustomerEmailUniqueCheck $check): string => $check->email,
            $checks,
        ))));

        foreach ($checks as $index => $check) {
            if (isset($conflictingIndexes[$index])) {
                continue;
            }

            foreach ($existingCustomers as $customer) {
                // Submitted customers are checked in memory using their final state.
                if (isset($candidateCustomerIds[$customer['id']])) {
                    continue;
                }

                if (!$this->isSameEmail($check->email, $customer['email'])) {
                    continue;
                }

                if (!$this->isInSameEmailUniquenessScope($check->boundSalesChannelId, $customer['boundSalesChannelId'], $isCustomerBoundToSalesChannel)) {
                    continue;
                }

                $this->addConflictingCheck($conflictingChecks, $conflictingIndexes, $index, $check);

                break;
            }
        }

        return $conflictingChecks;
    }

    private function isCustomerBoundToSalesChannel(): bool
    {
        return (bool) $this->systemConfigService->get('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');
    }

    private function isInSameEmailUniquenessScope(?string $boundSalesChannelId, ?string $existingBoundSalesChannelId, bool $isCustomerBoundToSalesChannel): bool
    {
        if (!$isCustomerBoundToSalesChannel) {
            return true;
        }

        if ($boundSalesChannelId === null || $existingBoundSalesChannelId === null) {
            return true;
        }

        return $boundSalesChannelId === $existingBoundSalesChannelId;
    }

    private function isSameEmail(string $email, string $comparedEmail): bool
    {
        return hash_equals(mb_strtolower($email), mb_strtolower($comparedEmail));
    }

    private function isSameCustomer(CustomerEmailUniqueCheck $check, CustomerEmailUniqueCheck $comparedCheck): bool
    {
        return $check->customerId !== null
            && $comparedCheck->customerId !== null
            && $check->customerId === $comparedCheck->customerId;
    }

    /**
     * @param list<CustomerEmailUniqueCheck> $conflictingChecks
     * @param array<int, true> $conflictingIndexes
     */
    private function addConflictingCheck(array &$conflictingChecks, array &$conflictingIndexes, int $index, CustomerEmailUniqueCheck $check): void
    {
        if (isset($conflictingIndexes[$index])) {
            return;
        }

        $conflictingChecks[] = $check;
        $conflictingIndexes[$index] = true;
    }

    /**
     * @param list<string> $emails
     *
     * @return list<array{id: string, email: string, boundSalesChannelId: string|null}>
     */
    private function fetchExistingCustomers(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        /** @var list<array{id: string, email: string, bound_sales_channel_id: string|null}> $customers */
        $customers = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) as `id`, `email`, LOWER(HEX(`bound_sales_channel_id`)) as `bound_sales_channel_id`
             FROM `customer`
             WHERE `email` IN (:emails)
             AND `guest` = 0',
            ['emails' => $emails],
            ['emails' => ArrayParameterType::STRING],
        );

        return array_map(static fn (array $customer): array => [
            'id' => $customer['id'],
            'email' => $customer['email'],
            'boundSalesChannelId' => $customer['bound_sales_channel_id'],
        ], $customers);
    }
}
