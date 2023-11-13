<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\System;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\System
 */
class SystemTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $system = new System();

        static::assertEquals(-1, $system->getTemplatePriority());
    }
}
