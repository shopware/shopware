<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(StoreApiCustomFieldMapper::class)]
class StoreApiCustomFieldMapperTest extends TestCase
{
    public function testMappingRemovesNotAllowedFields(): void
    {
        $connection = $this->createMock(Connection::class);

        $mapper = new StoreApiCustomFieldMapper($connection, ['customer' => [['name' => 'allowed', 'type' => 'string']]]);
        static::assertSame(['allowed' => 'yes'], $mapper->map('customer', new RequestDataBag(['bla' => 'foo', 'allowed' => 'yes'])));
    }

    public function testMappingDecodesFieldTypes(): void
    {
        $connection = $this->createMock(Connection::class);

        $mapper = new StoreApiCustomFieldMapper($connection, ['customer' => [
            ['name' => 'string', 'type' => CustomFieldTypes::TEXT],
            ['name' => 'int', 'type' => CustomFieldTypes::INT],
            ['name' => 'float', 'type' => CustomFieldTypes::FLOAT],
            ['name' => 'bool', 'type' => CustomFieldTypes::BOOL],
            ['name' => 'json', 'type' => CustomFieldTypes::JSON],
            ['name' => 'singleSelect', 'type' => CustomFieldTypes::SELECT],
            ['name' => 'multiSelect', 'type' => CustomFieldTypes::SELECT],
            ['name' => 'date', 'type' => CustomFieldTypes::DATETIME],
        ]]);

        $mappedValues = $mapper->map('customer', new RequestDataBag([
            'bla' => 'foo',
            'string' => 'yes',
            'int' => '1',
            'float' => '1.1',
            'bool' => 'true',
            'json' => new ParameterBag(['foo' => 'bar']),
            'singleSelect' => 'foo',
            'multiSelect' => new ParameterBag(['foo', 'bar']),
            'date' => '2020-01-01T00:00:00+00:00',
        ]));

        static::assertEquals(new \DateTimeImmutable('2020-01-01T00:00:00+00:00'), $mappedValues['date']);
        unset($mappedValues['date']);

        static::assertSame(
            [
                'string' => 'yes',
                'int' => 1,
                'float' => 1.1,
                'bool' => true,
                'json' => ['foo' => 'bar'],
                'singleSelect' => 'foo',
                'multiSelect' => ['foo', 'bar'],
            ],
            $mappedValues
        );
    }

    public function testInternalStorageWorks(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $mapper = new StoreApiCustomFieldMapper($connection);
        static::assertSame([], $mapper->map('customer', new RequestDataBag(['bla' => 'foo'])));
        static::assertSame([], $mapper->map('customer', new RequestDataBag(['bla' => 'foo'])));

        $mapper->reset();

        static::assertSame([], $mapper->map('customer', new RequestDataBag(['bla' => 'foo'])));
    }
}
