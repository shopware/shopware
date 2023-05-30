<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\NumberRange\ValueGenerator;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
class IncrementRedisStorageTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $numberRangeRepository;

    private MockObject&LockFactory $lockFactoryMock;

    private MockObject&\Redis $redisMock;

    private IncrementRedisStorage $storage;

    protected function setUp(): void
    {
        $this->numberRangeRepository = $this->getContainer()->get('number_range.repository');
        $this->lockFactoryMock = $this->createMock(LockFactory::class);
        $this->redisMock = $this->createMock('Redis');

        $this->storage = new IncrementRedisStorage($this->redisMock, $this->lockFactoryMock, $this->numberRangeRepository);
    }

    public function testReserveReturnsIncrementIfStartOfPatternIsLowerThenTheIncrement(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 5,
            'pattern' => 'n',
        ];

        $this->lockFactoryMock->expects(static::never())
            ->method('createLock');

        $this->redisMock->expects(static::once())
            ->method('incr')
            ->with($this->getKey($config['id']))
            ->willReturn(10);

        static::assertEquals(10, $this->storage->reserve($config));
    }

    public function testReserveWithoutStart(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => null,
            'pattern' => 'n',
        ];

        $this->lockFactoryMock->expects(static::never())
            ->method('createLock');

        $this->redisMock->expects(static::once())
            ->method('incr')
            ->with($this->getKey($config['id']))
            ->willReturn(10);

        static::assertEquals(10, $this->storage->reserve($config));
    }

    public function testReserveDoesNotLockIfIncrementValueEqualsStart(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 5,
            'pattern' => 'n',
        ];

        $this->lockFactoryMock->expects(static::never())
            ->method('createLock');

        $this->redisMock->expects(static::once())
            ->method('incr')
            ->with($this->getKey($config['id']))
            ->willReturn(5);

        static::assertEquals(5, $this->storage->reserve($config));
    }

    public function testReserveDoesSetStartValueIfItCanAcquireLock(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 10,
            'pattern' => 'n',
        ];

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects(static::once())
            ->method('acquire')
            ->willReturn(true);

        $lock->expects(static::once())
            ->method('release');

        $this->lockFactoryMock->expects(static::once())
            ->method('createLock')
            ->willReturn($lock);

        $this->redisMock->expects(static::once())
            ->method('incr')
            ->with($this->getKey($config['id']))
            ->willReturn(5);

        $this->redisMock->expects(static::once())
            ->method('incrBy')
            ->with($this->getKey($config['id']), 5)
            ->willReturn(10);

        static::assertEquals(10, $this->storage->reserve($config));
    }

    public function testReserveDoesNotSetStartValueIfItCanNotAcquireLock(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 10,
            'pattern' => 'n',
        ];

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects(static::once())
            ->method('acquire')
            ->willReturn(false);

        $lock->expects(static::never())
            ->method('release');

        $this->lockFactoryMock->expects(static::once())
            ->method('createLock')
            ->willReturn($lock);

        $this->redisMock->expects(static::once())
            ->method('incr')
            ->with($this->getKey($config['id']))
            ->willReturn(5);

        $this->redisMock->expects(static::never())
            ->method('incrBy');

        static::assertEquals(5, $this->storage->reserve($config));
    }

    public function testPreviewIfValueIsNotSetAndNoStart(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => null,
            'pattern' => 'n',
        ];

        $this->redisMock->expects(static::once())
            ->method('get')
            ->with($this->getKey($config['id']))
            ->willReturn(null);

        static::assertEquals(1, $this->storage->preview($config));
    }

    public function testPreviewWillReturnStartValueIfNoValueIsSet(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 10,
            'pattern' => 'n',
        ];

        $this->redisMock->expects(static::once())
            ->method('get')
            ->with($this->getKey($config['id']))
            ->willReturn(null);

        static::assertEquals(10, $this->storage->preview($config));
    }

    public function testPreviewWillReturnStartValueIfIncrementValueIsLower(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 10,
            'pattern' => 'n',
        ];

        $this->redisMock->expects(static::once())
            ->method('get')
            ->with($this->getKey($config['id']))
            ->willReturn(8);

        static::assertEquals(10, $this->storage->preview($config));
    }

    public function testList(): void
    {
        /** @var list<string> $numberRangeIds */
        $numberRangeIds = $this->numberRangeRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();

        $keys = array_map(fn (string $id) => [$this->getKey($id)], $numberRangeIds);
        $this->redisMock->expects(static::exactly(\count($keys)))
            ->method('get')
            ->willReturnOnConsecutiveCalls(10, 5, false);

        static::assertEquals([
            $numberRangeIds[0] => 10,
            $numberRangeIds[1] => 5,
        ], $this->storage->list());
    }

    public function testSet(): void
    {
        $configId = Uuid::randomHex();

        $this->redisMock->expects(static::once())
            ->method('set')
            ->with($this->getKey($configId), 10);

        $this->storage->set($configId, 10);
    }

    private function getKey(string $id): string
    {
        return 'number_range:' . $id;
    }
}
