<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\RemoveCustomerTagAction;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('business-ops')]
class RemoveCustomerTagActionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    private EntityRepository $flowRepository;

    private Connection $connection;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
    }

    public function testRemoveCustomerTagAction(): void
    {
        $this->createDataTest();

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $sequenceId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();

        $this->flowRepository->create([[
            'name' => 'Customer login',
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
                    'actionName' => RemoveCustomerTagAction::getName(),
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id') => 'test tag',
                            $this->ids->get('tag_id2') => 'test tag2',
                        ],
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id3') => 'test tag3',
                        ],
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
            ],
        ]], Context::createDefaultContext());

        $customerTag = $this->connection->fetchAllAssociative(
            'SELECT lower(hex(tag_id)) FROM customer_tag WHERE customer_id = (:customerId)',
            ['customerId' => Uuid::fromHexToBytes($this->ids->get('customer'))]
        );

        static::assertCount(2, $customerTag);

        $this->login($email, $password);

        $customerTag = $this->connection->fetchAllAssociative(
            'SELECT * FROM customer_tag WHERE customer_id = (:customerId)',
            ['customerId' => Uuid::fromHexToBytes($this->ids->get('tag_id'))]
        );

        static::assertCount(0, $customerTag);
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

    private function createCustomer(string $password, ?string $email = null): void
    {
        $this->customerRepository->create([
            [
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
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
                'vatIds' => ['DE123456789'],
                'company' => 'Test',
                'tags' => [
                    ['tagId' => $this->ids->get('tag_id'), 'name' => 'tag'],
                    ['tagId' => $this->ids->get('tag_id2'), 'name' => 'tag2'],
                ],
            ],
        ], Context::createDefaultContext());
    }

    private function createDataTest(): void
    {
        $this->getContainer()->get('tag.repository')->create([
            [
                'id' => $this->ids->create('tag_id'),
                'name' => 'test tag',
            ],
            [
                'id' => $this->ids->create('tag_id2'),
                'name' => 'test tag2',
            ],
            [
                'id' => $this->ids->create('tag_id3'),
                'name' => 'test tag3',
            ],
        ], Context::createDefaultContext());
    }
}
