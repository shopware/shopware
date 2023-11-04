<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1631703921MigrateLineItemsInCartRule;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1631703921MigrateLineItemsInCartRule
 */
class Migration1631703921MigrateLineItemsInCartRuleTest extends TestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testUpdate(): void
    {
        // moved to V6_5/Migration1669291632MigrateLineItemsInCartRuleTest.php
        (new Migration1631703921MigrateLineItemsInCartRule())->update(KernelLifecycleManager::getConnection());
    }
}
