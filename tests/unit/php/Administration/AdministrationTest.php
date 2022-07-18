<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;

/**
 * @internal
 *
 * @coversDefaultClass \Shopware\Administration\Administration
 */
class AdministrationTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $administration = new Administration();

        static::assertEquals(-1, $administration->getTemplatePriority());
    }
}
