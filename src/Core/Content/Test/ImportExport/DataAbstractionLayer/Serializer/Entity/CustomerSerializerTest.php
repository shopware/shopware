<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\CustomerSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;

class CustomerSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private EntityRepositoryInterface $customerGroupRepository;

    private EntityRepositoryInterface $paymentMethodRepository;

    private EntityRepositoryInterface $salesChannelRepository;

    private EntityRepositoryInterface $customerRepository;

    private CustomerSerializer $serializer;

    private string $customerGroupId = 'a536fe4ef675470f8cddfcc7f8360e4b';

    private string $paymentMethodId = '733530bc28f74bfbb43c32b595ac9fa0';

    public function setUp(): void
    {
        $this->customerGroupRepository = $this->getContainer()->get('customer_group.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);

        $this->serializer = new CustomerSerializer(
            $this->customerGroupRepository,
            $this->paymentMethodRepository,
            $this->salesChannelRepository
        );
        $this->serializer->setRegistry($serializerRegistry);
    }

    public function testSimple(): void
    {
        $salesChannel = $this->createSalesChannel();
        $this->createCustomerGroup();
        $this->createPaymentMethod();

        $config = new Config([], []);
        $customer = [
            'group' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'test customer group',
                    ],
                ],
            ],
            'defaultPaymentMethod' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'test payment method',
                    ],
                ],
            ],
            'salesChannel' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => $salesChannel['name'],
                    ],
                ],
            ],
        ];

        $deserialized = iterator_to_array($this->serializer->deserialize($config, $this->customerRepository->getDefinition(), $customer));

        static::assertSame($this->customerGroupId, $deserialized['groupId']);
        static::assertSame($this->customerGroupId, $deserialized['group']['id']);
        static::assertSame($this->paymentMethodId, $deserialized['defaultPaymentMethodId']);
        static::assertSame($this->paymentMethodId, $deserialized['defaultPaymentMethod']['id']);
        static::assertSame($salesChannel['id'], $deserialized['salesChannelId']);
        static::assertSame($salesChannel['id'], $deserialized['salesChannel']['id']);
    }

    public function testSupportsOnlyCountry(): void
    {
        $serializer = new CustomerSerializer(
            $this->customerGroupRepository,
            $this->paymentMethodRepository,
            $this->salesChannelRepository
        );

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            if ($entity === CustomerDefinition::ENTITY_NAME) {
                static::assertTrue($serializer->supports($entity));
            } else {
                static::assertFalse(
                    $serializer->supports($entity),
                    CustomerDefinition::class . ' should not support ' . $entity
                );
            }
        }
    }

    private function createCustomerGroup(): void
    {
        $this->customerGroupRepository->upsert([
            [
                'id' => $this->customerGroupId,
                'name' => 'test customer group',
            ],
        ], Context::createDefaultContext());
    }

    private function createPaymentMethod(): void
    {
        $this->paymentMethodRepository->upsert([
            [
                'id' => $this->paymentMethodId,
                'name' => 'test payment method',
            ],
        ], Context::createDefaultContext());
    }
}
