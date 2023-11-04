<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerEmailUniqueValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testSameCustomerEmailWithExistedBoundAccount(): void
    {
        $email = 'john.doe@example.com';

        $salesChannelContext1 = $this->createSalesChannelContext();
        $this->createCustomerOfSalesChannel($salesChannelContext1->getSalesChannel()->getId(), $email);

        $salesChannelParameters = [
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost2',
                ],
            ],
        ];

        $salesChannelContext2 = $this->createSalesChannelContext($salesChannelParameters);

        $constraint = new CustomerEmailUnique([
            'context' => $salesChannelContext2->getContext(),
            'salesChannelContext' => $salesChannelContext2,
        ]);

        $validation = new DataValidationDefinition('customer.email.update');

        $validation
            ->add('email', $constraint);

        $validator = $this->getContainer()->get(DataValidator::class);

        static::assertEmpty($validator->validate([
            'email' => $email,
        ], $validation));
    }

    public function testSameCustomerEmailOnSameSalesChannel(): void
    {
        $email = 'john.doe@example.com';

        $salesChannelContext1 = $this->createSalesChannelContext();
        $this->createCustomerOfSalesChannel($salesChannelContext1->getSalesChannel()->getId(), $email);

        $constraint = new CustomerEmailUnique([
            'context' => $salesChannelContext1->getContext(),
            'salesChannelContext' => $salesChannelContext1,
        ]);

        $validation = new DataValidationDefinition('customer.email.update');

        $validation->add('email', $constraint);

        $validator = $this->getContainer()->get(DataValidator::class);

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
            static::assertEquals($constraint->message, $violation->getMessageTemplate());
        }
    }

    public function testSameCustomerEmailWithExistedNonBoundAccount(): void
    {
        $email = 'john.doe@example.com';

        $salesChannelContext1 = $this->createSalesChannelContext();
        $this->createCustomerOfSalesChannel($salesChannelContext1->getSalesChannel()->getId(), $email);

        $constraint = new CustomerEmailUnique([
            'context' => $salesChannelContext1->getContext(),
            'salesChannelContext' => $salesChannelContext1,
        ]);

        $validation = new DataValidationDefinition('customer.email.update');

        $validation->add('email', $constraint);

        $validator = $this->getContainer()->get(DataValidator::class);

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
            static::assertEquals($constraint->message, $violation->getMessageTemplate());
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
            'password' => 'shopware',
            'boundSalesChannelId' => $boundToSalesChannel ? $salesChannelId : null,
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
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

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
