<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerEmailUniqueValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function tearDown(): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->delete('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');

        parent::tearDown();
    }

    public function testSameCustomerEmailWithExistingBoundAccountOnDifferentSalesChannel(): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', true);

        $email = 'john.doe@example.com';

        $this->createCustomerOfSalesChannel(TestDefaults::SALES_CHANNEL, $email);
        $constraint = $this->createConstraint(Uuid::randomHex());

        $validation = new DataValidationDefinition('customer.email.update');
        $validation->add('email', $constraint);

        $validator = static::getContainer()->get(DataValidator::class);
        $violations = [];
        try {
            $validator->validate(['email' => $email], $validation);
        } catch (ConstraintViolationException $exception) {
            $violations = $exception->getViolations();
        }
        static::assertCount(0, $violations, 'No violations are expected');
    }

    public function testSameCustomerEmailOnSameSalesChannel(): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', true);

        $email = 'john.doe@example.com';

        $this->createCustomerOfSalesChannel(TestDefaults::SALES_CHANNEL, $email);
        $constraint = $this->createConstraint(TestDefaults::SALES_CHANNEL);

        $validation = new DataValidationDefinition('customer.email.update');

        $validation->add('email', $constraint);

        $validator = static::getContainer()->get(DataValidator::class);

        try {
            $validator->validate([
                'email' => $email,
            ], $validation);

            static::fail('No exception is thrown');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $violations = $exception->getViolations();
            $violation = $violations->get(1);

            static::assertNotEmpty($violation);
            static::assertSame($constraint->message, $violation->getMessageTemplate());
        }
    }

    public function testSameCustomerEmailWithExistingNonBoundAccount(): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', true);

        $email = 'john.doe@example.com';

        $this->createCustomerOfSalesChannel(TestDefaults::SALES_CHANNEL, $email, false);

        $constraint = $this->createConstraint(TestDefaults::SALES_CHANNEL);

        $validation = new DataValidationDefinition('customer.email.update');

        $validation->add('email', $constraint);

        $validator = static::getContainer()->get(DataValidator::class);

        try {
            $validator->validate([
                'email' => $email,
            ], $validation);

            static::fail('No exception is thrown');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $violations = $exception->getViolations();
            $violation = $violations->get(1);

            static::assertNotEmpty($violation);
            static::assertSame($constraint->message, $violation->getMessageTemplate());
        }
    }

    private function createCustomerOfSalesChannel(string $salesChannelId, string $email, bool $boundToSalesChannel = true): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'boundSalesChannelId' => $boundToSalesChannel ? $salesChannelId : null,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => $salesChannelId,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function createConstraint(string $salesChannelId): CustomerEmailUnique
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')
            ->willReturn($salesChannelId);

        return new CustomerEmailUnique(salesChannelContext: $salesChannelContext);
    }
}
