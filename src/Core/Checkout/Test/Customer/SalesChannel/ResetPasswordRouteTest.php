<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class ResetPasswordRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testWithInvalidHash(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v1/account/recovery-password-confirm',
                [
                    'hash' => 'lol@lol.de',
                    'newPassword' => 'password123456',
                    'newPasswordConfirm' => 'password123456',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_RECOVERY_HASH_EXPIRED', $response['errors'][0]['code']);
    }

    public function testSuccessReset(): void
    {
        $customerId = $this->createCustomer('shopware1234', 'foo-test@test.de');

        $this->browser
            ->request(
                'POST',
                '/store-api/v1/account/recovery-password',
                [
                    'email' => 'foo-test@test.de',
                    'storefrontUrl' => 'http://localhost',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('customer_recovery.repository');

        /** @var CustomerRecoveryEntity $recovery */
        $recovery = $repo->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(CustomerRecoveryEntity::class, $recovery);

        $this->browser
            ->request(
                'POST',
                '/store-api/v1/account/recovery-password-confirm',
                [
                    'hash' => $recovery->getHash(),
                    'newPassword' => 'password123456',
                    'newPasswordConfirm' => 'password123456',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $this->browser
            ->request(
                'POST',
                '/store-api/v1/account/login',
                [
                    'email' => 'foo-test@test.de',
                    'password' => 'password123456',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);
    }

    private function createCustomer(string $password, ?string $email = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schoöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                    'salesChannels' => [
                        [
                            'id' => Defaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], $this->ids->context);

        return $customerId;
    }
}
