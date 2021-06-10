<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\AsyncTestPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @group store-api
 */
class ChangeProfileRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

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

    /**
     * @var string
     */
    private $customerId;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $email = Uuid::randomHex() . '@example.com';
        $this->customerId = $this->createCustomer('shopware', $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);

        $sources = array_column(array_column($response['errors'], 'source'), 'pointer');
        static::assertContains('/firstName', $sources);
        static::assertContains('/lastName', $sources);
        static::assertContains('/salutationId', $sources);
    }

    public function testChangeName(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                [
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertTrue($response['success']);

        $this->browser->request('GET', '/store-api/account/customer');
        $customer = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('Max', $customer['firstName']);
        static::assertSame('Mustermann', $customer['lastName']);
        static::assertSame($this->getValidSalutationId(), $customer['salutationId']);
    }

    public function testChangeProfileDataWithCommercialAccount(): void
    {
        $changeData = [
            'salutationId' => $this->getValidSalutationId(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'company' => 'Test Company',
            'vatIds' => [
                'DE123456789',
            ],
        ];
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertTrue($response['success']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->customerId));
        $customer = $this->customerRepository->search($criteria, $this->ids->context)->first();

        static::assertEquals(['DE123456789'], $customer->getVatIds());
        static::assertEquals($changeData['company'], $customer->getCompany());
        static::assertEquals($changeData['firstName'], $customer->getFirstName());
        static::assertEquals($changeData['lastName'], $customer->getLastName());
    }

    public function testChangeProfileDataWithCommercialAccountAndVatIdsIsEmpty(): void
    {
        $changeData = [
            'salutationId' => $this->getValidSalutationId(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'company' => 'Test Company',
            'vatIds' => [],
        ];
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertTrue($response['success']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->customerId));
        $customer = $this->customerRepository->search($criteria, $this->ids->context)->first();

        static::assertNull($customer->getVatIds());
        static::assertEquals($changeData['company'], $customer->getCompany());
        static::assertEquals($changeData['firstName'], $customer->getFirstName());
        static::assertEquals($changeData['lastName'], $customer->getLastName());
    }

    public function testChangeProfileDataWithPrivateAccount(): void
    {
        $changeData = [
            'salutationId' => $this->getValidSalutationId(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'firstName' => 'FirstName',
            'lastName' => 'LastName',
        ];
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertTrue($response['success']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->customerId));
        $customer = $this->customerRepository->search($criteria, $this->ids->context)->first();

        static::assertNull($customer->getVatIds());
        static::assertEquals('', $customer->getCompany());
        static::assertEquals($changeData['firstName'], $customer->getFirstName());
        static::assertEquals($changeData['lastName'], $customer->getLastName());
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('payment'),
                'name' => $this->ids->get('payment'),
                'active' => true,
                'handlerIdentifier' => AsyncTestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
            [
                'id' => $this->ids->create('payment2'),
                'name' => $this->ids->get('payment2'),
                'active' => true,
                'handlerIdentifier' => AsyncTestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
        ];

        $this->getContainer()->get('payment_method.repository')
            ->create($data, $this->ids->context);
    }
}
