<?php

namespace Shopware\Tests\Unit\Core\Framework\Api\Health\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\HealthCheck\Model\Result;
use Shopware\Core\Framework\Api\HealthCheck\Service\Check;
use Shopware\Core\Framework\Api\HealthCheck\Service\Manager;

class ManagerTest extends TestCase
{
    public function testHealthy()
    {
        $check1 = $this->createMock(Check\Database::class);
        $check2 = $this->createMock(Check\Cache::class);
        $manager = new Manager([$check1, $check2]);

        $check1->expects($this->once())
            ->method('run')
            ->willReturn($result1 = $this->createMock(Result::class));

        $check2->expects($this->once())
            ->method('run')
            ->willReturn($result2 = $this->createMock(Result::class));


        $check1->expects($this->any())
            ->method('dependsOn')
            ->willReturn([]);

        $check2->expects($this->any())
            ->method('dependsOn')
            ->willReturn([]);

        $result = $manager->healthCheck();
        $this->assertEquals([$result1, $result2], $result);
    }

    public function testDeadlocked()
    {
        $check1 = $this->createMock(Check::class);
        $check2 = $this->createMock(Check::class);

        $check1->expects($this->any())
            ->method('dependsOn')
            ->willReturn([Check\Database::class]);

        $check2->expects($this->any())
            ->method('dependsOn')
            ->willReturn([Check\Database::class]);

        $check1->expects($this->never())
            ->method('run');

        $check2->expects($this->never())
            ->method('run');

        $manager = new Manager([$check1, $check2]);

        $manager->healthCheck();
    }
}
