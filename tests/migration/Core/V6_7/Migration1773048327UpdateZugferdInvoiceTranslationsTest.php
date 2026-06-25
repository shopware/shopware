<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1773048327UpdateZugferdInvoiceTranslations;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1773048327UpdateZugferdInvoiceTranslations::class)]
class Migration1773048327UpdateZugferdInvoiceTranslationsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773048327, (new Migration1773048327UpdateZugferdInvoiceTranslations())->getCreationTimestamp());
    }

    public function testUpdateZugferdInvoiceTranslations(): void
    {
        $technicalNames = [
            ZugferdRenderer::TYPE,
            ZugferdEmbeddedRenderer::TYPE,
        ];

        foreach ($technicalNames as $technicalName) {
            $documentTypeId = $this->connection->fetchOne(
                'SELECT id FROM document_type WHERE technical_name = :technicalName',
                ['technicalName' => $technicalName]
            );

            if (!$documentTypeId) {
                continue;
            }

            $this->connection->executeStatement(
                'DELETE FROM `document_type_translation` WHERE document_type_id = :documentTypeId',
                ['documentTypeId' => $documentTypeId]
            );
        }

        $migration = new Migration1773048327UpdateZugferdInvoiceTranslations();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $translations = $this->connection->fetchAllAssociative(
            'SELECT name, language_id
                 FROM document_type_translation
                 WHERE document_type_id IN (
                     SELECT id FROM document_type WHERE technical_name IN (:technicalNames)
                 )
                 ORDER BY created_at',
            ['technicalNames' => $technicalNames],
            ['technicalNames' => ArrayParameterType::STRING]
        );

        $translations = \array_column($translations, 'name');
        sort($translations);

        static::assertSame(
            [
                'ZUGFeRD Invoice',
                'ZUGFeRD Invoice (embedded)',
                'ZUGFeRD Rechnung',
                'ZUGFeRD Rechnung (eingebettet)',
            ],
            $translations,
        );
    }
}
