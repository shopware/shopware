<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Profiling\Profiling;

/**
 * @internal
 *
 * @covers \Shopware\Core\Profiling\Profiling
 */
class ProfilingTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $profiling = new Profiling();

        static::assertEquals(-2, $profiling->getTemplatePriority());
    }
}
