<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1652166447AppLoadPriority;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1652166447AppLoadPriority
 */
class Migration1652166447AppLoadPriorityTest extends TestCase
{
    public function testRunMultipleOnes(): void
    {
        $connction = KernelLifecycleManager::getConnection();

        $m = new Migration1652166447AppLoadPriority();
        $m->update($connction);
        $m->update($connction);

        $columns = array_column(KernelLifecycleManager::getConnection()->fetchAllAssociative('SHOW COLUMNS FROM app'), 'Field');

        static::assertContains('template_load_priority', $columns);
    }
}
