<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1783944800AddGaranLabel;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1783944800AddGaranLabel::class)]
class Migration1783944800AddGaranLabelTest extends TestCase
{
    private const MAIL_TEMPLATE_TYPE = 'order_confirmation_mail';

    private readonly Connection $connection;

    private readonly Migration1783944800AddGaranLabel $migration;

    /**
     * @var array<array<string, mixed>>
     */
    private array $originalMailTranslations;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1783944800AddGaranLabel();

        foreach (['guarantee_confirmed', 'guarantee_months'] as $column) {
            try {
                $this->connection->executeStatement(\sprintf('ALTER TABLE `product` DROP COLUMN `%s`;', $column));
            } catch (\Throwable) {
            }
        }

        $this->originalMailTranslations = $this->fetchMailTranslations();
    }

    protected function tearDown(): void
    {
        foreach (['guarantee_confirmed', 'guarantee_months'] as $column) {
            try {
                $this->connection->executeStatement(\sprintf('ALTER TABLE `product` DROP COLUMN `%s`;', $column));
            } catch (\Throwable) {
            }
        }

        $this->migration->update($this->connection);

        foreach ($this->originalMailTranslations as $languageId => $translation) {
            $this->connection->update(
                'mail_template_translation',
                $translation,
                ['language_id' => $languageId, 'mail_template_id' => $this->getMailTemplateId()]
            );
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1783944800, $this->migration->getCreationTimestamp());
    }

    public function testMigrationRunsTwiceWithoutError(): void
    {
        $this->expectNotToPerformAssertions();

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);
    }

    public function testAddsGuaranteeColumnsToProductTable(): void
    {
        static::assertNull($this->fetchProductColumn('guarantee_confirmed'));
        static::assertNull($this->fetchProductColumn('guarantee_months'));

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $confirmedColumn = $this->fetchProductColumn('guarantee_confirmed');
        static::assertNotNull($confirmedColumn);
        static::assertStringStartsWith('tinyint', (string) $confirmedColumn['Type']);

        $monthsColumn = $this->fetchProductColumn('guarantee_months');
        static::assertNotNull($monthsColumn);
        static::assertStringStartsWith('int', (string) $monthsColumn['Type']);
    }

    public function testMailTemplateContainsGaranLabel(): void
    {
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $translations = $this->fetchMailTranslations();

        foreach ($translations as $translation) {
            static::assertStringContainsString('sw_garan_label', $translation['content_html']);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProductColumn(string $column): ?array
    {
        $result = $this->connection->fetchAssociative(
            'SHOW COLUMNS FROM `product` WHERE `Field` = :column',
            ['column' => $column]
        );

        return $result === false ? null : $result;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function fetchMailTranslations(): array
    {
        return $this->connection->fetchAllAssociativeIndexed(
            'SELECT `language_id`, `content_html`, `content_plain` FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId',
            ['mailTemplateId' => $this->getMailTemplateId()]
        );
    }

    private function getMailTemplateId(): string
    {
        $mailTemplateTypeId = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => self::MAIL_TEMPLATE_TYPE]
        );

        return (string) $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId AND system_default = 1',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );
    }
}
