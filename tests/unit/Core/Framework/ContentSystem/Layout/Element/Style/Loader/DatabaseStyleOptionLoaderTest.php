<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\DatabaseStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;
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
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'col-span', 'schema' => json_encode(['type' => 'integer', 'range' => ['min' => 1, 'max' => 12]]), 'app_name' => 'Acme'],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $options = $this->loader($connection, 'prod', $logger)->load();

        static::assertCount(1, $options);
        static::assertSame('col-span', $options[0]->name());
        static::assertSame('app:Acme', $options[0]->source());
        static::assertSame('integer', $options[0]->valueType()->type());
        static::assertTrue($options[0]->breakpointAware());
    }

    #[TestDox('loads a flat option with breakpointAware=false when the schema column declares it')]
    public function testLoadsFlatOptionBreakpointAwareFalse(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'brand-flat', 'schema' => json_encode(['type' => 'integer', 'breakpointAware' => false]), 'app_name' => 'Acme'],
        ]);

        $options = $this->loader($connection, 'prod')->load();

        static::assertCount(1, $options);
        static::assertSame('brand-flat', $options[0]->name());
        static::assertFalse($options[0]->breakpointAware());
    }

    #[TestDox('returns nothing in dev, where app options load from the filesystem instead')]
    public function testReturnsEmptyInDev(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        static::assertSame([], $this->loader($connection, 'dev', $logger)->load());
    }

    #[TestDox('skips a row whose persisted schema is not valid JSON and logs a warning')]
    public function testSkipsRowWithInvalidSchemaJson(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'col-span', 'schema' => '{not json', 'app_name' => 'Acme'],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('app:Acme:col-span'));

        $options = $this->loader($connection, 'prod', $logger)->load();

        static::assertSame([], $options);
    }

    #[TestDox('skips a row whose persisted schema is valid JSON but not a map and logs a warning')]
    public function testSkipsRowWithNonArraySchema(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'col-span', 'schema' => json_encode('just-a-string'), 'app_name' => 'Acme'],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('app:Acme:col-span'));

        $options = $this->loader($connection, 'prod', $logger)->load();

        static::assertSame([], $options);
    }

    #[TestDox('skips a row that fails validation while a valid sibling row survives, and logs a warning')]
    public function testSkipsRowThatFailsValidationWhileValidSiblingSurvives(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'col-span', 'schema' => json_encode(['type' => 'integer', 'range' => ['min' => 1, 'max' => 12]]), 'app_name' => 'Acme'],
            ['name' => 'broken', 'schema' => json_encode(['type' => 'object']), 'app_name' => 'Acme'],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::logicalAnd(
                static::stringContains('app:Acme:broken'),
                static::stringContains('not a valid choice'),
            ));

        $options = $this->loader($connection, 'prod', $logger)->load();

        static::assertCount(1, $options);
        static::assertSame('col-span', $options[0]->name());
    }

    private function loader(Connection $connection, string $environment, ?LoggerInterface $logger = null): DatabaseStyleOptionLoader
    {
        return new DatabaseStyleOptionLoader(
            new StyleOptionSpecificationSerializer(),
            $this->validator(),
            $connection,
            $environment,
            $logger ?? static::createStub(LoggerInterface::class),
        );
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
