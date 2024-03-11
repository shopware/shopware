<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;

/**
 * @internal
 */
#[CoversClass(Administration::class)]
class AdministrationTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $administration = new Administration();

        static::assertEquals(-1, $administration->getTemplatePriority());
    }
}
