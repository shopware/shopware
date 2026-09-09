<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\DatabaseStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDtoCollection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DatabaseStyleOptionLoader::class)]
class DatabaseStyleOptionLoaderTest extends TestCase
{
    #[TestDox('builds app-labelled specifications from the persisted rows in prod')]
    public function testLoadsActiveAppOptionsInProd(): void
    {
        $options = $this->loader([['name' => 'col-span', 'schema' => json_encode(['type' => 'integer', 'range' => ['min' => 1, 'max' => 12]], \JSON_THROW_ON_ERROR), 'app_name' => 'Acme']])->load();
        static::assertCount(1, $options);
        static::assertSame('col-span', $options[0]->name());
        static::assertSame('app:Acme', $options[0]->source());
        static::assertSame('integer', $options[0]->valueType()->type());
        static::assertTrue($options[0]->breakpointAware());
    }

    #[TestDox('loads a flat option with breakpointAware=false when the schema column declares it')]
    public function testLoadsFlatOptionBreakpointAwareFalse(): void
    {
        $options = $this->loader([['name' => 'brand-flat', 'schema' => json_encode(['type' => 'integer', 'breakpointAware' => false], \JSON_THROW_ON_ERROR), 'app_name' => 'Acme']])->load();

        static::assertCount(1, $options);
        static::assertSame('brand-flat', $options[0]->name());
        static::assertFalse($options[0]->breakpointAware());
    }

    #[TestDox('returns nothing in dev, where app options load from the filesystem instead')]
    public function testReturnsEmptyInDev(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        static::assertSame([], (new DatabaseStyleOptionLoader(new StyleOptionSpecificationSerializer(), static::createStub(ValidatorInterface::class), $connection, 'dev'))->load());
    }

    #[TestDox('rejects a persisted row without a style option name')]
    public function testRejectsEmptyName(): void
    {
        $this->expectExceptionObject(ContentSystemException::styleOptionLoadFailed('app:Acme:<unknown>', 'persisted row has no name and cannot be registered'));
        $this->loader([['name' => '', 'schema' => '{"type":"integer"}', 'app_name' => 'Acme']])->load();
    }

    #[TestDox('loads a persisted style option whose name is the string "0"')]
    public function testLoadsNameZero(): void
    {
        $options = $this->loader([['name' => '0', 'schema' => '{"type":"integer"}', 'app_name' => 'Acme']])->load();
        static::assertSame('0', $options[0]->name());
    }

    #[TestDox('rejects malformed persisted JSON and preserves the decoding error as its cause')]
    public function testRejectsMalformedJson(): void
    {
        try {
            $this->loader([['name' => 'broken', 'schema' => '{invalid', 'app_name' => 'Acme']])->load();
            static::fail('Expected malformed JSON to abort the database style option load.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::STYLE_OPTION_LOAD_FAILED, $exception->getErrorCode());
            static::assertStringContainsString('app:Acme:broken', $exception->getMessage());
            static::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    #[TestDox('rejects persisted JSON that does not decode to an array or map')]
    public function testRejectsScalarJson(): void
    {
        $this->expectExceptionObject(ContentSystemException::styleOptionLoadFailed(
            'app:Acme:broken',
            'Persisted schema must decode to an array/map, got string',
        ));
        $this->loader([['name' => 'broken', 'schema' => '"scalar"', 'app_name' => 'Acme']])->load();
    }

    #[TestDox('wraps a deserialization failure with the source-qualified style option name')]
    public function testRejectsDeserializationFailure(): void
    {
        $previous = new \RuntimeException('denormalize failure');
        $serializer = static::createStub(StyleOptionSpecificationSerializer::class);
        $serializer->method('denormalize')->willThrowException($previous);
        $loader = new DatabaseStyleOptionLoader($serializer, $this->emptyValidator(), $this->connection([['name' => 'broken', 'schema' => '{}', 'app_name' => 'Acme']]), 'prod');

        $this->expectExceptionObject(ContentSystemException::styleOptionLoadFailed('app:Acme:broken', 'Invalid schema: denormalize failure', $previous));
        $loader->load();
    }

    #[TestDox('validates all persisted style options together and rejects the entire load on a violation')]
    public function testValidatesAllRowsTogether(): void
    {
        $violations = new ConstraintViolationList([new ConstraintViolation('Invalid type', null, [], null, 'styleOptions[broken].type', '')]);
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(static::callback(static function (mixed $value): bool {
                static::assertInstanceOf(StyleOptionSpecificationDtoCollection::class, $value);
                static::assertSame(['good', 'broken'], array_keys($value->options));

                return true;
            }))
            ->willReturn($violations);
        $loader = $this->loader([
            ['name' => 'good', 'schema' => '{"type":"integer"}', 'app_name' => 'Acme'],
            ['name' => 'broken', 'schema' => '{"type":"object"}', 'app_name' => 'Acme'],
        ], $validator);
        $this->expectExceptionObject(ContentSystemException::styleOptionLoadValidationFailed($violations));
        $loader->load();
    }

    #[TestDox('does not swallow validator infrastructure failures')]
    public function testValidatorInfrastructureFailureIsNotSwallowed(): void
    {
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willThrowException(new \RuntimeException('validator infrastructure failure'));
        $loader = $this->loader([['name' => 'option', 'schema' => '{"type":"integer"}', 'app_name' => 'Acme']], $validator);

        $this->expectExceptionObject(new \RuntimeException('validator infrastructure failure'));
        $loader->load();
    }

    /**
     * @param list<array{name: string, schema: string, app_name: string}> $rows
     */
    private function loader(array $rows, ?ValidatorInterface $validator = null): DatabaseStyleOptionLoader
    {
        $validator ??= $this->emptyValidator();

        return new DatabaseStyleOptionLoader(new StyleOptionSpecificationSerializer(), $validator, $this->connection($rows), 'prod');
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
}
