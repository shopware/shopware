<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class Migration1638195971AddBaseAppUrlTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testColumnIsPresent(): void
    {
        $appColumns = array_column($this->getContainer()->get(Connection::class)->fetchAllAssociative('SHOW COLUMNS FROM app'), 'Field');

        static::assertContains('base_app_url', $appColumns);
    }
}
