<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Framework;

/**
 * @internal
 *
 * @coversDefaultClass \Shopware\Core\Framework\Framework
 */
class FrameworkTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $framework = new Framework();

        static::assertEquals(-1, $framework->getTemplatePriority());
    }
}
