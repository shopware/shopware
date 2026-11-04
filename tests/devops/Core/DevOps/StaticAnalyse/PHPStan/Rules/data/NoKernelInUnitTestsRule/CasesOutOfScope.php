<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Example\NoKernelInUnitTestsRule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 *
 * The migration suite runs against a database, so the kernel behaviours are legitimate there.
 */
class CasesOutOfScope extends TestCase
{
    use KernelTestBehaviour;

    public function testOne(): void
    {
        static::assertTrue(true);
    }
}
