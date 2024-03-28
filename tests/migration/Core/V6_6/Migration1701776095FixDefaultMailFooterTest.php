<?php

declare(strict_types=1);

namespace Shopware\Tests\Core\Migration\V6_6;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Migration\V6_6\Migration1701776095FixDefaultMailFooter;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_6\Migration1701776095FixDefaultMailFooter
 */
class Migration1701776095FixDefaultMailFooterTest extends TestCase
{
    use MigrationTestTrait;

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1701776095FixDefaultMailFooter();
        static::assertSame(1701776095, $migration->getCreationTimestamp());
    }
}
