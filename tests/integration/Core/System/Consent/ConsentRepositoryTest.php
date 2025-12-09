<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Consent;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\DTO\ConsentDTO;

/**
 * @internal
 */
class ConsentRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ConsentRepository $repository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->repository = new ConsentRepository($this->connection);
    }

    public function testCreate(): void
    {
        $this->repository->create('test-consent', 'global');

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM consent WHERE name = :name',
            ['name' => 'test-consent']
        );

        static::assertIsArray($result);
        static::assertSame('test-consent', $result['name']);
        static::assertSame('global', $result['storage']);
        static::assertIsString($result['id']);
        static::assertIsString($result['created_at']);
        static::assertNull($result['updated_at']);

        Uuid::fromBytesToHex($result['id']); // validate valid uuid
    }

    public function testCreateThrowsExceptionWhenNameAlreadyExists(): void
    {
        $this->repository->create('duplicate-consent', 'global');

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "duplicate-consent" already exists.');

        $this->repository->create('duplicate-consent', 'global');
    }

    public function testFetchAll(): void
    {
        $this->repository->create('consent-1', 'global');
        $this->repository->create('consent-2', 'global');

        $result = $this->repository->fetchAll();

        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(ConsentDTO::class, $result);
        static::assertSame('consent-1', $result[0]->name);
        static::assertSame('global', $result[0]->storage);
        static::assertSame('consent-2', $result[1]->name);
        static::assertSame('global', $result[1]->storage);
    }

    public function testFetchAllReturnsEmptyArrayWhenNoConsents(): void
    {
        $result = $this->repository->fetchAll();

        static::assertSame([], $result);
    }

    public function testCreateGeneratesUniqueIds(): void
    {
        $this->repository->create('consent-1', 'global');
        $this->repository->create('consent-2', 'global');

        $ids = $this->connection->fetchFirstColumn('SELECT id FROM consent');

        static::assertCount(2, $ids);
        static::assertNotEquals($ids[0], $ids[1]);
    }
}
