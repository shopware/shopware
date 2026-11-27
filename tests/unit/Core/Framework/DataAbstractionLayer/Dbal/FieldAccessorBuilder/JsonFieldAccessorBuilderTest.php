<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(JsonFieldAccessorBuilder::class)]
class JsonFieldAccessorBuilderTest extends TestCase
{
    #[DataProvider('castValueProvider')]
    public function testCastValueUsesTheTypeOfTheFieldAtTheJsonPath(string $accessor, string $expected): void
    {
        $builder = new JsonFieldAccessorBuilder(static::createStub(Connection::class));

        $field = new JsonField('data', 'data', [
            new DateTimeField('createdAt', 'createdAt'),
            new StringField('name', 'name'),
            new JsonField('child', 'child', [
                new DateField('birthday', 'birthday'),
            ]),
        ]);

        static::assertSame($expected, $builder->castValue(':value', $field, $accessor));
    }

    public static function castValueProvider(): \Generator
    {
        yield 'datetime field is cast to datetime with microseconds' => ['data.createdAt', 'CAST(:value AS datetime(6))'];
        yield 'date field in a nested mapping is cast to date' => ['data.child.birthday', 'CAST(:value AS DATE)'];
        yield 'string field keeps the plain value' => ['data.name', ':value'];
        yield 'key without mapping keeps the plain value' => ['data.unknown', ':value'];
        yield 'the whole JSON field keeps the plain value' => ['data', ':value'];
    }

    public function testCastValueKeepsTheValueOfANonJsonField(): void
    {
        $builder = new JsonFieldAccessorBuilder(static::createStub(Connection::class));

        static::assertSame(':value', $builder->castValue(':value', new DateTimeField('created_at', 'createdAt'), 'createdAt'));
    }
}
