<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\DatabaseTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), $validator, $connection, 'prod', $logger);
        $definitions = $loader->load();

        static::assertCount(1, $definitions);
        static::assertSame('App:Demo:Hero', $definitions[0]->name());
        static::assertSame('app:DemoApp', $definitions[0]->source());
    }

    #[TestDox('returns empty list in dev environment')]
    public function testReturnsEmptyListInDevEnvironment(): void
    {
        $connection = static::createStub(Connection::class);

        $loader = new DatabaseTypeLoader(
            new ElementTypeSpecificationSerializer(),
            static::createStub(ValidatorInterface::class),
            $connection,
            'dev',
            static::createStub(LoggerInterface::class),
        );

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

        $loader = new DatabaseTypeLoader(
            new ElementTypeSpecificationSerializer(),
            $validator,
            $connection,
            'prod',
            static::createStub(LoggerInterface::class),
        );
        $definitions = $loader->load();

        static::assertCount(1, $definitions);
        static::assertSame('<unknown>', $definitions[0]->name());
    }

    #[TestDox('skips a row that fails validation while a valid sibling row survives, and logs a warning')]
    public function testSkipsRowThatFailsValidationWhileValidRowSurvives(): void
    {
        $validSchema = json_encode([
            'meta' => [
                'label' => 'Hero',
                'description' => 'A hero banner.',
            ],
        ], \JSON_THROW_ON_ERROR);

        $invalidSchema = json_encode([
            'meta' => [
                'label' => '',
                'description' => '',
            ],
        ], \JSON_THROW_ON_ERROR);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'App:Good:Hero', 'schema' => $validSchema, 'app_name' => 'GoodApp'],
            ['name' => 'App:Bad:TypeB', 'schema' => $invalidSchema, 'app_name' => 'BrokenApp'],
        ]);

        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'types[App:Bad:TypeB].label', ''),
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturnOnConsecutiveCalls(new ConstraintViolationList(), $violations);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::logicalAnd(
                static::stringContains('App:Bad:TypeB'),
                static::stringContains('not be blank'),
            ));

        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), $validator, $connection, 'prod', $logger);
        $definitions = $loader->load();

        static::assertCount(1, $definitions);
        static::assertSame('App:Good:Hero', $definitions[0]->name());
    }

    #[TestDox('skips a row whose schema denormalizes to a wrong-typed DTO field while a valid sibling row survives, and logs a warning')]
    public function testSkipsRowWithWrongTypedSchemaFieldWhileValidRowSurvives(): void
    {
        $validSchema = json_encode([
            'meta' => [
                'label' => 'Hero',
                'description' => 'A hero banner.',
            ],
        ], \JSON_THROW_ON_ERROR);

        // "label" is an integer here instead of a string: it decodes to a valid array, so it passes the
        // is_array guard, but ElementTypeSpecificationDto's constructor requires a string and throws a
        // TypeError under strict_types — this must not abort the whole load.
        $wrongTypedSchema = json_encode([
            'meta' => [
                'label' => 123,
                'description' => 'x',
            ],
        ], \JSON_THROW_ON_ERROR);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'App:Good:Hero', 'schema' => $validSchema, 'app_name' => 'GoodApp'],
            ['name' => 'App:Broken:TypeError', 'schema' => $wrongTypedSchema, 'app_name' => 'BrokenApp'],
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('App:Broken:TypeError'));

        $loader = new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), $validator, $connection, 'prod', $logger);
        $definitions = $loader->load();

        static::assertCount(1, $definitions);
        static::assertSame('App:Good:Hero', $definitions[0]->name());
    }

    #[TestDox('skips a row with malformed JSON schema and logs a warning')]
    public function testSkipsRowWithMalformedJsonSchema(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'App:Broken', 'schema' => '{invalid json', 'app_name' => 'BrokenApp'],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('App:Broken'));

        $loader = new DatabaseTypeLoader(
            new ElementTypeSpecificationSerializer(),
            static::createStub(ValidatorInterface::class),
            $connection,
            'prod',
            $logger,
        );

        static::assertSame([], $loader->load());
    }

    #[TestDox('skips a row whose schema is not a JSON map and logs a warning')]
    public function testSkipsRowWithNonArraySchema(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'App:Broken', 'schema' => json_encode('just-a-string'), 'app_name' => 'BrokenApp'],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('App:Broken'));

        $loader = new DatabaseTypeLoader(
            new ElementTypeSpecificationSerializer(),
            static::createStub(ValidatorInterface::class),
            $connection,
            'prod',
            $logger,
        );

        static::assertSame([], $loader->load());
    }
}
