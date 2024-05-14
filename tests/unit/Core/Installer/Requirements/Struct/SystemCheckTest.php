<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Requirements\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopware\Core\Installer\Requirements\Struct\SystemCheck;

/**
 * @internal
 */
#[CoversClass(RequirementCheck::class)]
#[CoversClass(SystemCheck::class)]
class SystemCheckTest extends TestCase
{
    public function testGetters(): void
    {
        $check = new SystemCheck('name', RequirementCheck::STATUS_SUCCESS, 'requiredValue', 'installedValue');

        static::assertEquals('name', $check->getName());
        static::assertEquals('requiredValue', $check->getRequiredValue());
        static::assertEquals('installedValue', $check->getInstalledValue());
        static::assertEquals(RequirementCheck::STATUS_SUCCESS, $check->getStatus());
    }

    public function testEmptyNameThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Empty name for RequirementCheck provided.');
        new SystemCheck('', RequirementCheck::STATUS_SUCCESS, 'installedValue', 'status');
    }

    public function testWrongStatusThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid status for RequirementCheck, got "wrongStatus", allowed values are "success", "error", "warning".');
        new SystemCheck('name', 'wrongStatus', 'installedValue', 'status');
    }
}
