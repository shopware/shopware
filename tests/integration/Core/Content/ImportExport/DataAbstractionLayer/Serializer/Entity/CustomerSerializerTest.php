<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\CustomerSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CustomerSerializer::class)]
class CustomerSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private EntityRepository $customerGroupRepository;

    private EntityRepository $salesChannelRepository;

    private EntityRepository $customerRepository;

    private CustomerSerializer $serializer;

    private string $customerGroupId = 'a536fe4ef675470f8cddfcc7f8360e4b';

    protected function setUp(): void
    {
        $this->customerGroupRepository = static::getContainer()->get('customer_group.repository');
        $this->salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $serializerRegistry = static::getContainer()->get(SerializerRegistry::class);

        $this->serializer = new CustomerSerializer(
            $this->customerGroupRepository,
            $this->salesChannelRepository
        );
        $this->serializer->setRegistry($serializerRegistry);
    }

    public function testSimple(): void
    {
        $salesChannel = $this->createSalesChannel();
        $this->createCustomerGroup();

        $config = new Config([], [], []);
        $customer = [
            'group' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'test customer group',
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
            'boundSalesChannel' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => $salesChannel['name'],
                    ],
                ],
            ],
        ];

        $deserialized = $this->serializer->deserialize($config, $this->customerRepository->getDefinition(), $customer);

        static::assertIsNotArray($deserialized);

        $deserialized = \iterator_to_array($deserialized);

        static::assertSame($this->customerGroupId, $deserialized['group']['id']);
        static::assertSame($salesChannel['id'], $deserialized['salesChannel']['id']);
        static::assertSame($salesChannel['id'], $deserialized['boundSalesChannel']['id']);
    }

    public function testSupportsOnlyCountry(): void
    {
        $serializer = new CustomerSerializer(
            $this->customerGroupRepository,
            $this->salesChannelRepository
        );

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
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
}
