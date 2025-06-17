<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Service\Manager;

/**
 * @internal
 */
#[CoversClass(Manager::class)]
class ManagerTest extends TestCase
{
    private Privileges&MockObject $privileges;

    private Connection&MockObject $connection;

    private Manager $manager;

    private Context $context;

    protected function setUp(): void
    {
        $this->privileges = $this->createMock(Privileges::class);
        $this->connection = $this->createMock(Connection::class);
        $this->manager = new Manager($this->privileges, $this->connection);
        $this->context = Context::createDefaultContext();
    }

    public function testEnable(): void
    {
        $serviceIds = ['service1', 'service2', 'service3'];

        $this->connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with('SELECT LOWER(HEX(id)) FROM app WHERE self_managed = 1')
            ->willReturn($serviceIds);

        $this->privileges
            ->expects($this->once())
            ->method('acceptAllForApps')
            ->with($serviceIds, $this->context);

        $this->manager->enable($this->context);
    }

    public function testDisable(): void
    {
        $serviceIds = ['service1', 'service2', 'service3'];

        $this->connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with('SELECT LOWER(HEX(id)) FROM app WHERE self_managed = 1')
            ->willReturn($serviceIds);

        $this->privileges
            ->expects($this->once())
            ->method('revokeAllForApps')
            ->with($serviceIds, $this->context);

        $this->manager->disable($this->context);
    }

    public function testEnableWithNoServices(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with('SELECT LOWER(HEX(id)) FROM app WHERE self_managed = 1')
            ->willReturn([]);

        $this->privileges
            ->expects($this->once())
            ->method('acceptAllForApps')
            ->with([], $this->context);

        $this->manager->enable($this->context);
    }

    public function testDisableWithNoServices(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with('SELECT LOWER(HEX(id)) FROM app WHERE self_managed = 1')
            ->willReturn([]);

        $this->privileges
            ->expects($this->once())
            ->method('revokeAllForApps')
            ->with([], $this->context);

        $this->manager->disable($this->context);
    }
}
