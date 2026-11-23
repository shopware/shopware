<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Requirements\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Requirements\Struct\RequirementCheck;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RequirementCheck::class)]
class RequirementCheckTest extends TestCase
{
    public function testEmptyNameThrowsException(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Empty name for RequirementCheck provided.'));
        new class('', RequirementCheck::STATUS_SUCCESS) extends RequirementCheck {
        };
    }

    public function testWrongStatusThrowsException(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Invalid status for RequirementCheck, got "wrongStatus", allowed values are "success", "error", "warning".'));
        new class('name', 'wrongStatus') extends RequirementCheck {
        };
    }
}
