<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Profiling\Profiling;

/**
 * @internal
 */
#[CoversClass(Profiling::class)]
class ProfilingTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $profiling = new Profiling();

        static::assertEquals(-2, $profiling->getTemplatePriority());
    }
}
