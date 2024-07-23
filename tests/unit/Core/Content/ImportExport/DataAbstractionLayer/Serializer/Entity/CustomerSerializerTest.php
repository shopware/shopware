<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\CustomerSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomerSerializer::class)]
class CustomerSerializerTest extends TestCase
{
    /**
     * @param array<string, string|array<string, array<string, array<string, string>>>> $data
     * @param array<string, string|array<string, string>> $expected
     * @param array<string, array<string, string>> $cacheEntities
     */
    #[DataProvider('provideCustomerData')]
    public function testDeserialize(
        array $data,
        array $expected,
        array $cacheEntities,
    ): void {
        $repositoryMock = $this->createMock(EntityRepository::class);
        $serializer = new CustomerSerializer(
            $repositoryMock,
            $repositoryMock,
            $repositoryMock,
            $cacheEntities['cacheCustomerGroups'],
            $cacheEntities['cachePaymentMethods'],
            $cacheEntities['cacheSalesChannels'],
        );

        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $serializer->setRegistry($this->createMock(SerializerRegistry::class));

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $customerDefinition, $data));
        static::assertSame($expected, $result);
    }

    public static function provideCustomerData(): \Generator
    {
        yield 'test basic data' => [
            'data' => self::createData(),
            'expected' => self::createExpected(),
            'cacheEntities' => self::createCacheEntities(),
        ];
        yield 'none of the data processed by the deserializer is passed' => [
            'data' => [],
            'expected' => [],
            'cacheEntities' => [
                'cacheCustomerGroups' => [],
                'cachePaymentMethods' => [],
                'cacheSalesChannels' => [],
            ],
        ];
        yield 'bounded sales channel and sales channel are different' => [
            'data' => self::createData([
                'boundSalesChannel' => [
                    'translations' => [
                        'DEFAULT' => [
                            'name' => 'bound_sales_channel_name',
                        ],
                    ],
                ],
            ]),
            'expected' => self::createExpected([
                'boundSalesChannel' => [
                    'id' => 'boundSalesChannelId',
                ],
            ]),
            'cacheEntities' => self::createCacheEntities([
                'cacheSalesChannels' => [
                    'bound_sales_channel_name' => 'boundSalesChannelId',
                ],
            ]),
        ];
    }

    /**
     * @param array<string, string|array<string, array<string, array<string, string>>>> $overrides
     *
     * @return array<string, string|array<string, array<string, array<string, string>>>>
     */
    private static function createData(array $overrides = []): array
    {
        $data = array_merge([
            'group' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'group_name',
                    ],
                ],
            ],
            'salesChannel' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'sales_channel_name',
                    ],
                ],
            ],
        ], $overrides);

        return $data;
    }

    /**
     * @param array<string, string|array<string, string>> $overrides
     *
     * @return array<string, string|array<string, string>>
     */
    private static function createExpected(array $overrides = []): array
    {
        $data = array_merge([
            'group' => [
                'id' => 'groupId',
            ],
            'salesChannel' => [
                'id' => 'salesChannelId',
            ],
        ], $overrides);

        return $data;
    }

    /**
     * @param array<string, array<string, string>> $overrides
     *
     * @return array<string, array<string, string>>
     */
    private static function createCacheEntities(array $overrides = []): array
    {
        return array_merge_recursive([
            'cacheCustomerGroups' => [
                'group_name' => 'groupId',
            ],
            'cachePaymentMethods' => [
                'payment_name' => 'paymentId',
            ],
            'cacheSalesChannels' => [
                'sales_channel_name' => 'salesChannelId',
            ],
        ], $overrides);
    }
}
