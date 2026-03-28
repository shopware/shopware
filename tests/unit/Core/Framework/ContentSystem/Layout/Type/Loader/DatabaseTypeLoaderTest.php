<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\DatabaseTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
                'label' => 'Hero',
                'description' => 'A hero banner.',
            ],
        ], \JSON_THROW_ON_ERROR);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'App:Demo:Hero', 'schema' => $schema, 'app_name' => 'DemoApp'],
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), $validator, $connection, 'prod');
        $definitions = $loader->load();

        static::assertCount(1, $definitions);
        static::assertSame('App:Demo:Hero', $definitions[0]->name());
        static::assertSame('app:DemoApp', $definitions[0]->source());
    }

    #[TestDox('returns empty list in dev environment')]
    public function testReturnsEmptyListInDevEnvironment(): void
    {
        $connection = static::createStub(Connection::class);

        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), static::createStub(ValidatorInterface::class), $connection, 'dev');

        static::assertSame([], $loader->load());
    }

    #[TestDox('uses unknown placeholder when database row has empty name')]
    public function testUsesUnknownPlaceholderForEmptyName(): void
    {
        $schema = json_encode([
            'meta' => [
                'label' => 'Unnamed',
                'description' => 'An element with no name.',
            ],
        ], \JSON_THROW_ON_ERROR);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => '', 'schema' => $schema, 'app_name' => 'DemoApp'],
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), $validator, $connection, 'prod');
        $definitions = $loader->load();

        static::assertCount(1, $definitions);
        static::assertSame('<unknown>', $definitions[0]->name());
    }

    #[TestDox('throws batch validation exception when database contains invalid schemas')]
    public function testThrowsWhenDatabaseContainsInvalidSchema(): void
    {
        $schema = json_encode([
            'meta' => [
                'label' => '',
                'description' => '',
            ],
        ], \JSON_THROW_ON_ERROR);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'App:Bad:TypeA', 'schema' => $schema, 'app_name' => 'BrokenApp'],
            ['name' => 'App:Bad:TypeB', 'schema' => $schema, 'app_name' => 'BrokenApp'],
        ]);

        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'types[App:Bad:TypeA].label', ''),
            new ConstraintViolation('This value should not be blank.', null, [], null, 'types[App:Bad:TypeB].label', ''),
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations);

        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), $validator, $connection, 'prod');

        $this->expectExceptionObject(ContentSystemException::elementTypesInvalid($violations));
        $loader->load();
    }

    #[TestDox('throws domain exception when database row contains malformed JSON schema')]
    public function testThrowsDomainExceptionWhenDatabaseRowContainsMalformedJsonSchema(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'App:Broken', 'schema' => '{invalid json', 'app_name' => 'BrokenApp'],
        ]);

        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), static::createStub(ValidatorInterface::class), $connection, 'prod');

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('App:Broken');
        $loader->load();
    }
}
