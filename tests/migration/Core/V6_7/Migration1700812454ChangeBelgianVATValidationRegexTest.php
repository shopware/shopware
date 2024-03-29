<?php

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1700812454ChangeBelgianVATValidationRegex;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_7\Migration1700812454ChangeBelgianVATValidationRegex
 */
#[Package('core')]
class Migration1700812454ChangeBelgianVATValidationRegexTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1700812454, (new Migration1700812454ChangeBelgianVATValidationRegex())->getCreationTimestamp());
    }

    public function testMigrate(): void
    {
        $this->migrate();
        $vatIdPattern = $this->connection->fetchOne('SELECT `vat_id_pattern` FROM `country` WHERE `iso` = "BE"');
        $this->assertEquals('(BE)?[01][0-9]{9}', $vatIdPattern);
    }

    private function migrate(): void
    {
        (new Migration1700812454ChangeBelgianVATValidationRegex())->update($this->connection);
    }
}
