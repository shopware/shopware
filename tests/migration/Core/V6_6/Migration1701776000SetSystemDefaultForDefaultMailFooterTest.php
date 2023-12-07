<?php

declare(strict_types=1);

namespace Shopware\Tests\Core\Migration\V6_6;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Migration\V6_6\Migration1701776000SetSystemDefaultForDefaultMailFooter;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_6\Migration1701776000SetSystemDefaultForDefaultMailFooter
 */
class Migration1701776000SetSystemDefaultForDefaultMailFooterTest extends TestCase
{
    use MigrationTestTrait;

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1701776000SetSystemDefaultForDefaultMailFooter();
        static::assertSame(1701776000, $migration->getCreationTimestamp());
    }
}
