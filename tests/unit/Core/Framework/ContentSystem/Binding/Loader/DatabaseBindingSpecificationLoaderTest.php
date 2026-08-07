<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
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
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'media-picker', 'schema' => json_encode($this->validSchema()), 'app_name' => 'Acme'],
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $specifications = $this->loader($connection, 'prod', $validator, $logger)->load();

        static::assertCount(1, $specifications);
        static::assertSame('media-picker', $specifications[0]->id());
        static::assertSame('Sw:Media:Image', $specifications[0]->type());
        static::assertSame('app:Acme', $specifications[0]->source());
    }

    #[TestDox('returns an empty list in dev environment without querying the database')]
    public function testReturnsEmptyListInDevEnvironmentWithoutQuerying(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        static::assertSame([], $this->loader($connection, 'dev', $validator)->load());
    }

    #[TestDox('queries only bindings belonging to active apps')]
    public function testQueriesOnlyActiveApps(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(static::stringContains('WHERE a.active = 1'))
            ->willReturn([]);

        $this->loader($connection, 'prod', static::createStub(ValidatorInterface::class))->load();
    }

    #[TestDox('skips a row with a blank name while a valid sibling row survives, and logs a warning')]
    public function testSkipsRowWithBlankNameWhileValidSiblingSurvives(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => '', 'schema' => json_encode($this->validSchema()), 'app_name' => 'Acme'],
            ['name' => 'media-picker', 'schema' => json_encode($this->validSchema()), 'app_name' => 'Acme'],
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::logicalAnd(
                static::stringContains('app:Acme:<unknown>'),
                static::stringContains('no name'),
            ));

        $specifications = $this->loader($connection, 'prod', $validator, $logger)->load();

        static::assertCount(1, $specifications);
        static::assertSame('media-picker', $specifications[0]->id());
    }

    #[TestDox('loads a persisted binding whose name is the string "0" instead of silently skipping it')]
    public function testLoadsBindingNamedZero(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => '0', 'schema' => json_encode($this->validSchema()), 'app_name' => 'Acme'],
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $specifications = $this->loader($connection, 'prod', $validator, $logger)->load();

        static::assertCount(1, $specifications);
        static::assertSame('0', $specifications[0]->id());
    }

    #[TestDox('skips a row whose persisted schema is not valid JSON and logs a warning')]
    public function testSkipsRowWithInvalidJsonSchema(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'media-picker', 'schema' => '{not json', 'app_name' => 'Acme'],
        ]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('app:Acme:media-picker'));

        $specifications = $this->loader($connection, 'prod', $validator, $logger)->load();

        static::assertSame([], $specifications);
    }

    #[TestDox('skips a row whose persisted schema is valid JSON but not a map and logs a warning')]
    public function testSkipsRowWithNonArraySchema(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'media-picker', 'schema' => json_encode('a-string'), 'app_name' => 'Acme'],
        ]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('app:Acme:media-picker'));

        $specifications = $this->loader($connection, 'prod', $validator, $logger)->load();

        static::assertSame([], $specifications);
    }

    #[TestDox('skips a row that fails validation while a valid sibling row survives, and logs a warning')]
    public function testSkipsRowThatFailsValidationWhileValidSiblingSurvives(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'media-picker', 'schema' => json_encode($this->validSchema()), 'app_name' => 'Acme'],
            ['name' => 'broken', 'schema' => json_encode(['type' => '', 'label' => 'Broken']), 'app_name' => 'Acme'],
        ]);

        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'bindings[broken].type', ''),
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')
            ->willReturnOnConsecutiveCalls(new ConstraintViolationList(), $violations);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::logicalAnd(
                static::stringContains('app:Acme:broken'),
                static::stringContains('not be blank'),
            ));

        $specifications = $this->loader($connection, 'prod', $validator, $logger)->load();

        static::assertCount(1, $specifications);
        static::assertSame('media-picker', $specifications[0]->id());
    }

    #[TestDox('skips a row when the validator throws, keeping the valid sibling, instead of aborting the whole load')]
    public function testSkipsRowWhenValidatorThrowsWhileValidSiblingSurvives(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'media-picker', 'schema' => json_encode($this->validSchema()), 'app_name' => 'Acme'],
            ['name' => 'boom', 'schema' => json_encode($this->validSchema()), 'app_name' => 'Acme'],
        ]);

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')
            ->willReturnCallback(function (): ConstraintViolationList {
                static $calls = 0;
                if (++$calls === 2) {
                    throw new \RuntimeException('validator infrastructure failure');
                }

                return new ConstraintViolationList();
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('app:Acme:boom'));

        $specifications = $this->loader($connection, 'prod', $validator, $logger)->load();

        static::assertCount(1, $specifications);
        static::assertSame('media-picker', $specifications[0]->id());
    }

    #[TestDox('skips a row whose schema cannot be deserialized into a specification, keeping the valid sibling, instead of aborting the whole load')]
    public function testSkipsRowWithUnprocessableSchemaWhileValidSiblingSurvives(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'boom', 'schema' => json_encode(['type' => 'x']), 'app_name' => 'Acme'],
            ['name' => 'media-picker', 'schema' => json_encode($this->validSchema()), 'app_name' => 'Acme'],
        ]);

        $serializer = static::createStub(BindingSpecificationSerializer::class);
        $serializer->method('denormalize')->willReturnCallback(
            static function (array $data): BindingSpecificationDto {
                if (($data['type'] ?? null) === 'x') {
                    throw new \RuntimeException('denormalize failure');
                }

                return (new BindingSpecificationSerializer())->denormalize($data);
            }
        );

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(static::stringContains('app:Acme:boom'));

        $loader = new DatabaseBindingSpecificationLoader('prod', $connection, $logger, $serializer, $validator);
        $specifications = $loader->load();

        static::assertCount(1, $specifications);
        static::assertSame('media-picker', $specifications[0]->id());
    }

    /**
     * @return array<string, mixed>
     */
    private function validSchema(): array
    {
        return [
            'type' => 'Sw:Media:Image',
            'label' => 'Media picker',
            'resolves' => [
                'media' => [
                    'loader' => 'entity',
                    'config' => ['entity' => 'media', 'property' => 'mediaId'],
                ],
            ],
            'inputs' => [
                'mediaId' => ['required' => false],
            ],
        ];
    }

    private function loader(
        Connection $connection,
        string $environment,
        ValidatorInterface $validator,
        ?LoggerInterface $logger = null,
    ): DatabaseBindingSpecificationLoader {
        return new DatabaseBindingSpecificationLoader(
            $environment,
            $connection,
            $logger ?? static::createStub(LoggerInterface::class),
            new BindingSpecificationSerializer(),
            $validator,
        );
    }
}
