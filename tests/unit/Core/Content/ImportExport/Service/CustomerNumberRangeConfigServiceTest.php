<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\Service\CustomerNumberRangeConfigService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CustomerNumberRangeConfigService::class)]
class CustomerNumberRangeConfigServiceTest extends TestCase
{
    public function testReturnsPatternConfigWithHexadecimalId(): void
    {
        $connection = static::createStub(Connection::class);
        $result = static::createStub(Result::class);
        $configurationId = '00000000000000000000000000000001';
        $result->method('fetchAssociative')->willReturn([
            'id' => Uuid::fromHexToBytes($configurationId),
            'pattern' => 'CUSTOMER-{n}',
        ]);

        $queryBuilder = $this->createQueryBuilderStub($result);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $service = new CustomerNumberRangeConfigService($connection);

        $salesChannelId = Uuid::randomHex();
        static::assertSame([
            'id' => $configurationId,
            'pattern' => 'CUSTOMER-{n}',
        ], $service->getPatternConfig($salesChannelId));
        static::assertSame($configurationId, $service->getPatternConfigId($salesChannelId));
    }

    public function testReturnsNullWhenNoPatternConfigExists(): void
    {
        $connection = static::createStub(Connection::class);
        $result = static::createStub(Result::class);
        $result->method('fetchAssociative')->willReturn(false);

        $connection->method('createQueryBuilder')->willReturn($this->createQueryBuilderStub($result));

        $service = new CustomerNumberRangeConfigService($connection);

        static::assertNull($service->getPatternConfig(null));
        static::assertNull($service->getPatternConfigId(null));
    }

    public function testCachesPatternConfigPerSalesChannel(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturnCallback(
            function (): QueryBuilder {
                $result = static::createStub(Result::class);
                $result->method('fetchAssociative')->willReturn([
                    'id' => Uuid::fromHexToBytes('00000000000000000000000000000001'),
                    'pattern' => 'CUSTOMER-{n}',
                ]);

                return $this->createQueryBuilderStub($result);
            }
        );
        $salesChannelId = Uuid::randomHex();
        $service = new CustomerNumberRangeConfigService($connection);

        static::assertSame([
            'id' => '00000000000000000000000000000001',
            'pattern' => 'CUSTOMER-{n}',
        ], $service->getPatternConfig($salesChannelId));
        static::assertSame(
            $service->getPatternConfig($salesChannelId),
            $service->getPatternConfig($salesChannelId),
        );
    }

    public function testResetClearsPatternConfigCache(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('createQueryBuilder')->willReturnCallback(
            function (): QueryBuilder {
                $result = static::createStub(Result::class);
                $result->method('fetchAssociative')->willReturn([
                    'id' => Uuid::fromHexToBytes('00000000000000000000000000000001'),
                    'pattern' => 'CUSTOMER-{n}',
                ]);

                return $this->createQueryBuilderStub($result);
            }
        );
        $salesChannelId = Uuid::randomHex();
        $service = new CustomerNumberRangeConfigService($connection);

        $service->getPatternConfig($salesChannelId);
        $service->reset();
        $service->getPatternConfig($salesChannelId);
    }

    public function testBindsSalesChannelIdAsBinaryUuid(): void
    {
        $salesChannelId = Uuid::randomHex();
        $boundParameters = [];
        $result = static::createStub(Result::class);
        $result->method('fetchAssociative')->willReturn(false);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('innerJoin')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('addOrderBy')->willReturn($queryBuilder);
        $queryBuilder->method('leftJoin')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturnCallback(
            static function (string $name, mixed $value) use (&$boundParameters, $queryBuilder): QueryBuilder {
                $boundParameters[$name] = $value;

                return $queryBuilder;
            }
        );
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $service = new CustomerNumberRangeConfigService($connection);
        $service->getPatternConfig($salesChannelId);

        static::assertSame(CustomerDefinition::ENTITY_NAME, $boundParameters['typeName']);
        static::assertSame(Uuid::fromHexToBytes($salesChannelId), $boundParameters['salesChannelId']);
    }

    public function testCachesMissingPatternConfig(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = static::createStub(Result::class);
        $result->method('fetchAssociative')->willReturn(false);
        $connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderStub($result));

        $service = new CustomerNumberRangeConfigService($connection);
        $salesChannelId = Uuid::randomHex();

        static::assertNull($service->getPatternConfig($salesChannelId));
        static::assertNull($service->getPatternConfig($salesChannelId));
    }

    public function testKeepsPatternConfigsSeparatePerSalesChannel(): void
    {
        $connection = $this->createMock(Connection::class);
        $configurationIds = [
            '00000000000000000000000000000001',
            '00000000000000000000000000000002',
        ];
        $queryNumber = 0;
        $connection->expects($this->exactly(2))
            ->method('createQueryBuilder')
            ->willReturnCallback(function () use (&$queryNumber, $configurationIds): QueryBuilder {
                $configurationId = $configurationIds[$queryNumber++];
                $result = static::createStub(Result::class);
                $result->method('fetchAssociative')->willReturn([
                    'id' => Uuid::fromHexToBytes($configurationId),
                    'pattern' => 'CUSTOMER-{n}',
                ]);

                return $this->createQueryBuilderStub($result);
            });

        $service = new CustomerNumberRangeConfigService($connection);

        static::assertSame(
            '00000000000000000000000000000001',
            $service->getPatternConfig(Uuid::randomHex())['id'] ?? null,
        );
        static::assertSame(
            '00000000000000000000000000000002',
            $service->getPatternConfig(Uuid::randomHex())['id'],
        );
    }

    /**
     * @param array<string, mixed>|false $configuration
     */
    #[DataProvider('invalidPatternConfigurations')]
    public function testReturnsNullForInvalidPatternConfiguration(array|false $configuration): void
    {
        $result = static::createStub(Result::class);
        $result->method('fetchAssociative')->willReturn($configuration);
        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($this->createQueryBuilderStub($result));

        static::assertNull((new CustomerNumberRangeConfigService($connection))->getPatternConfig(null));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>|false}>
     */
    public static function invalidPatternConfigurations(): iterable
    {
        yield 'no result' => [false];
        yield 'missing id' => [['pattern' => 'CUSTOMER-{n}']];
        yield 'missing pattern' => [['id' => Uuid::fromHexToBytes('00000000000000000000000000000001')]];
    }

    private function createQueryBuilderStub(Result $result): QueryBuilder
    {
        $queryBuilder = static::createStub(QueryBuilder::class);
        foreach (['select', 'from', 'innerJoin', 'where', 'setParameter', 'orderBy', 'addOrderBy', 'leftJoin', 'andWhere'] as $method) {
            $queryBuilder->method($method)->willReturn($queryBuilder);
        }
        $queryBuilder->method('executeQuery')->willReturn($result);

        return $queryBuilder;
    }
}
