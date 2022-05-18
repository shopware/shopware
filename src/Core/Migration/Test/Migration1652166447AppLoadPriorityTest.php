<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1652166447AppLoadPriority;

/**
 * @internal
 */
class Migration1652166447AppLoadPriorityTest extends TestCase
{
    use KernelTestBehaviour;

    public function testRunMultipleOnes(): void
    {
        $connction = $this->getContainer()->get(Connection::class);

        $m = new Migration1652166447AppLoadPriority();
        $m->update($connction);
        $m->update($connction);

        $columns = array_column($this->getContainer()->get(Connection::class)->fetchAll('SHOW COLUMNS FROM app'), 'Field');

        static::assertContains('template_load_priority', $columns);
    }
}
