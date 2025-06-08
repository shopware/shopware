<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1711461579FixDefaultMailFooter;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1711461579FixDefaultMailFooter::class)]
class Migration1711461579FixDefaultMailFooterTest extends TestCase
{
    use MigrationTestTrait;

    private Migration1711461579FixDefaultMailFooter $migration;

    private Connection $connection;

    private string $germanLanguageId;

    protected function setUp(): void
    {
        $this->migration = new Migration1711461579FixDefaultMailFooter();
        $this->connection = KernelLifecycleManager::getConnection();
        $germanLanguageId = $this->fetchLanguageId($this->connection, 'de-DE');
        static::assertIsString($germanLanguageId);
        $this->germanLanguageId = $germanLanguageId;
    }

    public function testMigration(): void
    {
        $this->ensureMailFooterHasTypo();

        $this->migration->update($this->connection);

        static::assertFalse($this->hasMailFooterTypo());
    }

    private function ensureMailFooterHasTypo(): void
    {
        if ($this->hasMailFooterTypo()) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE mail_header_footer_translation
            SET footer_plain = REPLACE(footer_plain, \'Adresse:\', \'Addresse:\')
            WHERE language_id = :id',
            ['id' => $this->germanLanguageId],
            ['id' => ParameterType::BINARY]
        );

        static::assertTrue($this->hasMailFooterTypo());
    }

    private function hasMailFooterTypo(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1
             FROM mail_header_footer_translation
             WHERE footer_plain LIKE \'%Addresse:%\'
                AND language_id = :id;',
            ['id' => $this->germanLanguageId],
            ['id' => ParameterType::BINARY]
        );
    }
}
