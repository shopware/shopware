<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class Migration1635237551ScriptTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTablesIsPresent(): void
    {
        $appScriptColumns = array_column($this->getContainer()->get(Connection::class)->fetchAllAssociative('SHOW COLUMNS FROM script'), 'Field');

        static::assertContains('id', $appScriptColumns);
        static::assertContains('script', $appScriptColumns);
        static::assertContains('hook', $appScriptColumns);
        static::assertContains('name', $appScriptColumns);
        static::assertContains('active', $appScriptColumns);
        static::assertContains('app_id', $appScriptColumns);
        static::assertContains('created_at', $appScriptColumns);
        static::assertContains('updated_at', $appScriptColumns);
    }
}
