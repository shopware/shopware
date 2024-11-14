<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Flow;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\ChangeCustomerGroupAction;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('services-settings')]
class ChangeCustomerGroupActionTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private EntityRepository $flowRepository;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
    }

    public function testChangeCustomerGroupAction(): void
    {
        $this->createDataTest();

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        $sequenceId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();

        $this->flowRepository->create([[
            'name' => 'Create Order',
            'eventName' => CustomerLoginEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'sequences' => [
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => $ruleId,
                    'actionName' => null,
                    'config' => [],
                    'position' => 1,
                    'rule' => [
                        'id' => $ruleId,
                        'name' => 'Test rule',
                        'priority' => 1,
                        'conditions' => [
                            ['type' => (new AlwaysValidRule())->getName()],
                        ],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => ChangeCustomerGroupAction::getName(),
                    'config' => [
                        'customerGroupId' => $this->ids->get('customer_group_id'),
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
            ],
        ]], Context::createDefaultContext());

        $this->login($email, 'shopware');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Test group'));
        /** @var CustomerGroupEntity $customerGroupId */
        $customerGroupId = $this->getContainer()->get('customer_group.repository')->search($criteria, Context::createDefaultContext())->first();

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(new Criteria([$this->ids->get('customer')]), Context::createDefaultContext())->first();

        static::assertSame($customerGroupId->getId(), $customer->getGroupId());
    }

    private function login(?string $email = null, ?string $password = null): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    private function createCustomer(?string $email = null): void
    {
        $customer = [
            'id' => $this->ids->create('customer'),
            'salesChannelId' => $this->ids->get('sales-channel'),
            'defaultShippingAddress' => [
                'id' => $this->ids->create('address'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
            ],
            'defaultBillingAddressId' => $this->ids->get('address'),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
            'vatIds' => ['DE123456789'],
            'company' => 'Test',
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $this->customerRepository->create([$customer], Context::createDefaultContext());
    }

    private function createDataTest(): void
    {
        $this->getContainer()->get('customer_group.repository')->create([
            [
                'id' => $this->ids->create('customer_group_id'),
                'name' => 'Test group',
            ],
        ], Context::createDefaultContext());
    }
}
