<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(DatabaseBindingSpecificationLoader::class)]
class DatabaseBindingSpecificationLoaderTest extends TestCase
{
    #[TestDox('builds an app-labelled binding specification from a valid persisted row in prod')]
    public function testLoadsActiveAppBindingFromPersistedRowInProd(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'from-media-library', 'schema' => json_encode($this->validSchema()), 'app_name' => 'Acme'],
        ]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())->method('validate')->willReturn(new ConstraintViolationList());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $specifications = $this->loader($connection, 'prod', $validator, $logger)->load();

        static::assertCount(1, $specifications);
        static::assertSame('from-media-library', $specifications[0]->id());
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

        $this->loader($connection, 'prod', $this->createMock(ValidatorInterface::class))->load();
    }

    #[TestDox('skips a row whose persisted schema is not valid JSON and logs a warning')]
    public function testSkipsRowWithInvalidJsonSchema(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'from-media-library', 'schema' => '{not json', 'app_name' => 'Acme'],
        ]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('app:Acme:from-media-library'));

        $specifications = $this->loader($connection, 'prod', $validator, $logger)->load();

        static::assertSame([], $specifications);
    }

    #[TestDox('skips a row whose persisted schema is valid JSON but not a map and logs a warning')]
    public function testSkipsRowWithNonArraySchema(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'from-media-library', 'schema' => json_encode('a-string'), 'app_name' => 'Acme'],
        ]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('app:Acme:from-media-library'));

        $specifications = $this->loader($connection, 'prod', $validator, $logger)->load();

        static::assertSame([], $specifications);
    }

    #[TestDox('skips a row that fails validation while a valid sibling row survives, and logs a warning')]
    public function testSkipsRowThatFailsValidationWhileValidSiblingSurvives(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'from-media-library', 'schema' => json_encode($this->validSchema()), 'app_name' => 'Acme'],
            ['name' => 'broken', 'schema' => json_encode(['type' => '', 'label' => 'Broken']), 'app_name' => 'Acme'],
        ]);

        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'bindings[broken].type', ''),
        ]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->exactly(2))
            ->method('validate')
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
        static::assertSame('from-media-library', $specifications[0]->id());
    }

    /**
     * @return array<string, mixed>
     */
    private function validSchema(): array
    {
        return [
            'type' => 'Sw:Media:Image',
            'label' => 'From media library',
            'resolves' => [
                'media' => [
                    'loader' => 'entity',
                    'config' => ['entity' => 'media', 'property' => 'mediaId'],
                ],
            ],
            'inputs' => [
                'mediaId' => [],
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
            new BindingSpecificationSerializer(),
            $validator,
            $connection,
            $environment,
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }
}
