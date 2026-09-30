<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DatabaseBindingSpecificationLoader::class)]
class DatabaseBindingSpecificationLoaderTest extends TestCase
{
    #[TestDox('builds an app-labelled binding specification from a valid persisted row in prod')]
    public function testLoadsActiveAppBindingFromPersistedRowInProd(): void
    {
        $specifications = $this->loader([['name' => 'media-picker', 'schema' => json_encode($this->validSchema(), \JSON_THROW_ON_ERROR), 'app_name' => 'Acme']])->load();
        static::assertSame('media-picker', $specifications[0]->id());
        static::assertSame('app:Acme', $specifications[0]->source());
    }

    #[TestDox('returns an empty list in dev environment without querying the database')]
    public function testReturnsEmptyListInDevEnvironmentWithoutQuerying(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        static::assertSame([], (new DatabaseBindingSpecificationLoader('dev', $connection, new BindingSpecificationSerializer(), $this->emptyValidator()))->load());
    }

    #[TestDox('rejects a persisted row without a binding name')]
    public function testRejectsEmptyName(): void
    {
        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed('app:Acme:<unknown>', 'persisted row has no name and cannot be registered'));
        $this->loader([['name' => '', 'schema' => json_encode($this->validSchema(), \JSON_THROW_ON_ERROR), 'app_name' => 'Acme']])->load();
    }

    #[TestDox('loads a persisted binding whose name is the string "0" instead of silently skipping it')]
    public function testLoadsBindingNamedZero(): void
    {
        $specifications = $this->loader([['name' => '0', 'schema' => json_encode($this->validSchema(), \JSON_THROW_ON_ERROR), 'app_name' => 'Acme']])->load();
        static::assertSame('0', $specifications[0]->id());
    }

    #[TestDox('rejects malformed persisted JSON and preserves the decoding error as its cause')]
    public function testRejectsMalformedJson(): void
    {
        try {
            $this->loader([['name' => 'broken', 'schema' => '{invalid', 'app_name' => 'Acme']])->load();
            static::fail('Expected malformed JSON to abort the database binding specification load.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::BINDING_SPECIFICATION_LOAD_FAILED, $exception->getErrorCode());
            static::assertStringContainsString('app:Acme:broken', $exception->getMessage());
            static::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    #[TestDox('rejects persisted JSON that does not decode to an array or map')]
    public function testRejectsScalarJson(): void
    {
        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            'app:Acme:broken',
            'Persisted schema must decode to an array/map, got string',
        ));
        $this->loader([['name' => 'broken', 'schema' => '"scalar"', 'app_name' => 'Acme']])->load();
    }

    #[TestDox('wraps a deserialization failure with the source-qualified binding id')]
    public function testRejectsDeserializationFailure(): void
    {
        $previous = new \RuntimeException('denormalize failure');
        $serializer = static::createStub(BindingSpecificationSerializer::class);
        $serializer->method('denormalize')->willThrowException($previous);
        $loader = new DatabaseBindingSpecificationLoader('prod', $this->connection([['name' => 'broken', 'schema' => '{}', 'app_name' => 'Acme']]), $serializer, $this->emptyValidator());
        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed('app:Acme:broken', 'Invalid schema: denormalize failure', $previous));
        $loader->load();
    }

    #[TestDox('validates equal bare binding ids from different apps independently')]
    public function testValidatesEqualBareIdsFromDifferentAppsIndependently(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(static::callback(static function (mixed $value): bool {
                static::assertInstanceOf(BindingSpecificationDtoCollection::class, $value);
                static::assertSame(['app:First:shared', 'app:Second:shared'], array_keys($value->bindings));

                return true;
            }))
            ->willReturn(new ConstraintViolationList());

        $specifications = $this->loader([
            ['name' => 'shared', 'schema' => json_encode($this->validSchema(), \JSON_THROW_ON_ERROR), 'app_name' => 'First'],
            ['name' => 'shared', 'schema' => json_encode($this->validSchema(), \JSON_THROW_ON_ERROR), 'app_name' => 'Second'],
        ], $validator)->load();

        static::assertCount(2, $specifications);
    }

    #[TestDox('validates all persisted bindings together and rejects the entire load on a violation')]
    public function testValidatesAllRowsTogetherAndFailsTheWholeLoad(): void
    {
        $violations = new ConstraintViolationList([new ConstraintViolation('Invalid type', null, [], null, 'bindings[broken].type', '')]);
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations);
        $loader = $this->loader([
            ['name' => 'good', 'schema' => json_encode($this->validSchema(), \JSON_THROW_ON_ERROR), 'app_name' => 'Acme'],
            ['name' => 'broken', 'schema' => json_encode($this->validSchema(), \JSON_THROW_ON_ERROR), 'app_name' => 'Acme'],
        ], $validator);
        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadValidationFailed($violations));
        $loader->load();
    }

    #[TestDox('does not swallow validator infrastructure failures')]
    public function testValidatorInfrastructureFailureIsNotSwallowed(): void
    {
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willThrowException(new \RuntimeException('validator infrastructure failure'));
        $loader = $this->loader([['name' => 'binding', 'schema' => json_encode($this->validSchema(), \JSON_THROW_ON_ERROR), 'app_name' => 'Acme']], $validator);
        $this->expectExceptionObject(new \RuntimeException('validator infrastructure failure'));
        $loader->load();
    }

    /**
     * @param list<array{name: string, schema: string, app_name: string}> $rows
     */
    private function loader(array $rows, ?ValidatorInterface $validator = null): DatabaseBindingSpecificationLoader
    {
        return new DatabaseBindingSpecificationLoader('prod', $this->connection($rows), new BindingSpecificationSerializer(), $validator ?? $this->emptyValidator());
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
     * @return array<string, mixed>
     */
    private function validSchema(): array
    {
        return ['type' => 'Sw:Media:Image', 'label' => 'Media picker', 'resolves' => [], 'inputs' => []];
    }
}
