<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoKernelInUnitTestsRule;

use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * A fixture trait that looks harmless but composes the kernel behaviour; loaded by the rule test so the
 * composition can be reflected.
 */
trait LocalFixtureBehaviour
{
    use KernelTestBehaviour;
}
