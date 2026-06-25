<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\DatabaseStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(DatabaseStyleOptionLoader::class)]
class DatabaseStyleOptionLoaderTest extends TestCase
{
    #[TestDox('returns nothing in dev, where app options load from the filesystem instead')]
    public function testReturnsEmptyInDev(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        static::assertSame([], $this->loader($connection, 'dev')->load());
    }

    #[TestDox('builds app-labelled specifications from the persisted rows in prod')]
    public function testLoadsActiveAppOptionsInProd(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'col-span', 'schema' => json_encode(['type' => 'integer', 'range' => ['min' => 1, 'max' => 12]]), 'app_name' => 'Acme'],
        ]);

        $options = $this->loader($connection, 'prod')->load();

        static::assertCount(1, $options);
        static::assertSame('col-span', $options[0]->name());
        static::assertSame('app:Acme', $options[0]->source());
        static::assertSame('integer', $options[0]->valueType()->type());
    }

    #[TestDox('fails hard when a persisted schema is not valid JSON')]
    public function testFailsOnInvalidSchemaJson(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'col-span', 'schema' => '{not json', 'app_name' => 'Acme'],
        ]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/col-span/');

        $this->loader($connection, 'prod')->load();
    }

    #[TestDox('fails hard when a persisted schema is valid JSON but not a map')]
    public function testFailsOnNonArraySchema(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'col-span', 'schema' => json_encode('just-a-string'), 'app_name' => 'Acme'],
        ]);

        $this->expectExceptionObject(
            ContentSystemException::styleOptionLoadFailed('col-span', 'persisted schema must decode to an array/map, got string')
        );

        $this->loader($connection, 'prod')->load();
    }

    #[TestDox('fails batch validation when a persisted option declaration is malformed')]
    public function testFailsValidationForMalformedPersistedOption(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'broken', 'schema' => json_encode(['type' => 'object']), 'app_name' => 'Acme'],
        ]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/options\[broken\]\.type/');

        $this->loader($connection, 'prod')->load();
    }

    private function loader(Connection $connection, string $environment): DatabaseStyleOptionLoader
    {
        return new DatabaseStyleOptionLoader(
            new StyleOptionSpecificationSerializer(),
            $this->validator(),
            $connection,
            $environment,
        );
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
