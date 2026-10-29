<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueCheck;
use Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @codeCoverageIgnore Tested via integration tests.
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber\CustomerEmailUniqueSubscriberTest
 */
#[Package('checkout')]
class CustomerEmailUniqueSubscriber implements EventSubscriberInterface
{
    private const UNIQUENESS_COLUMNS = ['email', 'guest', 'bound_sales_channel_id'];

    public function __construct(
        private readonly CustomerEmailUniqueChecker $customerEmailUniqueChecker,
        private readonly WriteOutcome $writeOutcome,
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
        $checks = [];
        $paths = [];

        foreach ($this->writeOutcome->resultingRows($event->getCommandsForEntity(CustomerDefinition::ENTITY_NAME), self::UNIQUENESS_COLUMNS) as $customer) {
            $email = $customer->getResultingValue('email');
            if (!\is_string($email)) {
                continue;
            }

            $customerId = Uuid::fromBytesToHex($customer->primaryKey['id']);
            $boundSalesChannelId = $customer->getResultingValue('bound_sales_channel_id');

            $checks[] = new CustomerEmailUniqueCheck(
                email: $email,
                customerId: $customerId,
                boundSalesChannelId: \is_string($boundSalesChannelId) ? Uuid::fromBytesToHex($boundSalesChannelId) : null,
                guest: (bool) $customer->getResultingValue('guest'),
            );
            $paths[$customerId] = $customer->path;
        }

        if ($checks === []) {
            return;
        }

        $conflictingChecks = $this->customerEmailUniqueChecker->findConflictingChecks(...$checks);
        if ($conflictingChecks === []) {
            return;
        }

        $violations = new ConstraintViolationList();

        foreach ($conflictingChecks as $check) {
            \assert($check->customerId !== null);

            $this->addViolation($violations, $paths[$check->customerId], $check->email);
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    private function addViolation(ConstraintViolationList $violations, string $path, string $email): void
    {
        $message = 'The email address {{ email }} is already in use.';

        $violations->add(new ConstraintViolation(
            str_replace('{{ email }}', $email, $message),
            $message,
            ['{{ email }}' => $email],
            null,
            $path . '/email',
            $email,
            null,
            CustomerEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE,
        ));
    }
}
