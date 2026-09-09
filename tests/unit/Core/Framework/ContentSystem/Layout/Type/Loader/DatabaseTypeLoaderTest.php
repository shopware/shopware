<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\DatabaseTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDtoCollection;
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
        $definitions = $this->loader([['name' => 'App:Demo:Hero', 'schema' => json_encode($this->schema(), \JSON_THROW_ON_ERROR), 'app_name' => 'DemoApp']])->load();
        static::assertSame('App:Demo:Hero', $definitions[0]->name());
        static::assertSame('app:DemoApp', $definitions[0]->source());
    }

    #[TestDox('returns empty list in dev environment')]
    public function testReturnsEmptyListInDevEnvironment(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        static::assertSame([], (new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), static::createStub(ValidatorInterface::class), $connection, 'dev'))->load());
    }

    #[TestDox('rejects a persisted row without an element type name')]
    public function testRejectsEmptyName(): void
    {
        $this->expectExceptionObject(ContentSystemException::elementTypeLoadFailed('app:DemoApp:<unknown>', 'persisted row has no name and cannot be registered'));
        $this->loader([['name' => '', 'schema' => json_encode($this->schema(), \JSON_THROW_ON_ERROR), 'app_name' => 'DemoApp']])->load();
    }

    #[TestDox('loads a persisted element type whose name is the string "0"')]
    public function testLoadsNameZero(): void
    {
        $definitions = $this->loader([['name' => '0', 'schema' => json_encode($this->schema(), \JSON_THROW_ON_ERROR), 'app_name' => 'DemoApp']])->load();
        static::assertSame('0', $definitions[0]->name());
    }

    #[TestDox('rejects malformed persisted JSON and preserves the decoding error as its cause')]
    public function testRejectsMalformedJson(): void
    {
        try {
            $this->loader([['name' => 'broken', 'schema' => '{invalid', 'app_name' => 'DemoApp']])->load();
            static::fail('Expected malformed JSON to abort the database element type load.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::ELEMENT_TYPE_LOAD_FAILED, $exception->getErrorCode());
            static::assertStringContainsString('app:DemoApp:broken', $exception->getMessage());
            static::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    #[TestDox('rejects persisted JSON that does not decode to an array or map')]
    public function testRejectsScalarJson(): void
    {
        $this->expectExceptionObject(ContentSystemException::elementTypeLoadFailed(
            'app:DemoApp:broken',
            'Persisted schema must decode to an array/map, got string',
        ));
        $this->loader([['name' => 'broken', 'schema' => json_encode('scalar', \JSON_THROW_ON_ERROR), 'app_name' => 'DemoApp']])->load();
    }

    #[TestDox('wraps a deserialization failure with the source-qualified element type name')]
    public function testRejectsDeserializationFailure(): void
    {
        $previous = new \RuntimeException('denormalize failure');
        $serializer = static::createStub(ElementTypeSpecificationSerializer::class);
        $serializer->method('denormalize')->willThrowException($previous);
        $loader = new DatabaseTypeLoader($serializer, $this->emptyValidator(), $this->connection([['name' => 'broken', 'schema' => '{}', 'app_name' => 'DemoApp']]), 'prod');
        $this->expectExceptionObject(ContentSystemException::elementTypeLoadFailed('app:DemoApp:broken', 'Invalid schema: denormalize failure', $previous));
        $loader->load();
    }

    #[TestDox('validates all persisted element types together and rejects the entire load on a violation')]
    public function testValidatesAllRowsTogetherAndFailsTheWholeLoad(): void
    {
        $violations = new ConstraintViolationList([new ConstraintViolation('Invalid label', null, [], null, 'types[App:Bad:Type].label', '')]);
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(static::callback(static function (mixed $value): bool {
                static::assertInstanceOf(ElementTypeSpecificationDtoCollection::class, $value);
                static::assertSame(['App:Good:Hero', 'App:Bad:Type'], array_keys($value->types));

                return true;
            }))
            ->willReturn($violations);
        $loader = $this->loader([
            ['name' => 'App:Good:Hero', 'schema' => json_encode($this->schema(), \JSON_THROW_ON_ERROR), 'app_name' => 'GoodApp'],
            ['name' => 'App:Bad:Type', 'schema' => json_encode($this->schema(), \JSON_THROW_ON_ERROR), 'app_name' => 'BadApp'],
        ], $validator);
        $this->expectExceptionObject(ContentSystemException::elementTypeLoadValidationFailed($violations));
        $loader->load();
    }

    #[TestDox('does not swallow validator infrastructure failures')]
    public function testValidatorInfrastructureFailureIsNotSwallowed(): void
    {
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willThrowException(new \RuntimeException('validator infrastructure failure'));
        $loader = $this->loader([['name' => 'App:Demo:Hero', 'schema' => json_encode($this->schema(), \JSON_THROW_ON_ERROR), 'app_name' => 'DemoApp']], $validator);

        $this->expectExceptionObject(new \RuntimeException('validator infrastructure failure'));
        $loader->load();
    }

    /**
     * @param list<array{name: string, schema: string, app_name: string}> $rows
     */
    private function loader(array $rows, ?ValidatorInterface $validator = null): DatabaseTypeLoader
    {
        return new DatabaseTypeLoader(new ElementTypeSpecificationSerializer(), $validator ?? $this->emptyValidator(), $this->connection($rows), 'prod');
    }

    /**
     * @param list<array{name: string, schema: string, app_name: string}> $rows
     */
    private function connection(array $rows): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return $connection;
    }

    private function emptyValidator(): ValidatorInterface
    {
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        return $validator;
    }

    /**
     * @return array{meta: array{label: string, description: string}}
     */
    private function schema(): array
    {
        return ['meta' => ['label' => 'Hero', 'description' => 'A hero banner.']];
    }
}
