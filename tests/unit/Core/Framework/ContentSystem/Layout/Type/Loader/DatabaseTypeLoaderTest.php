<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\DatabaseTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(DatabaseTypeLoader::class)]
class DatabaseTypeLoaderTest extends TestCase
{
    #[TestDox('loads element type definitions from the database in production environment')]
    public function testLoadsDefinitionsFromDatabaseInProductionEnvironment(): void
    {
        $schema = json_encode([
            'meta' => [
                'name' => 'App:Demo:Hero',
                'label' => 'Hero',
                'description' => 'A hero banner.',
                'vendor' => 'DemoApp',
            ],
        ], \JSON_THROW_ON_ERROR);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'App:Demo:Hero', 'schema' => $schema, 'app_name' => 'DemoApp'],
        ]);

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), $validator, $connection, 'prod');
        $definitions = $loader->load();

        static::assertCount(1, $definitions);
        static::assertSame('App:Demo:Hero', $definitions[0]->name());
    }

    #[TestDox('returns empty list in dev environment')]
    public function testReturnsEmptyListInDevEnvironment(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), $validator, $connection, 'dev');

        static::assertEmpty($loader->load());
    }

    #[TestDox('throws when database contains invalid schema')]
    public function testThrowsWhenDatabaseContainsInvalidSchema(): void
    {
        $schema = json_encode([
            'meta' => [
                'name' => '',
                'label' => '',
                'description' => '',
                'vendor' => '',
            ],
        ], \JSON_THROW_ON_ERROR);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => '', 'schema' => $schema, 'app_name' => 'BrokenApp'],
        ]);

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), $validator, $connection, 'prod');

        $this->expectException(ContentSystemException::class);
        $loader->load();
    }
}
