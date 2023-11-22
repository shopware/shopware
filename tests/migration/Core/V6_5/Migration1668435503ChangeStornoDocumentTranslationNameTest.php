<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1668435503ChangeStornoDocumentTranslationName;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1668435503ChangeStornoDocumentTranslationName::class)]
class Migration1668435503ChangeStornoDocumentTranslationNameTest extends TestCase
{
    use MigrationTestTrait;

    /**
     * @throws Exception
     */
    public function testStornoDocumentTranslationNameUpdated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1668435503ChangeStornoDocumentTranslationName();
        $migration->update($connection);

        $cancellationId = $connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => StornoRenderer::TYPE]
        );
        $enLangId = $connection->fetchOne(
            'SELECT `language`.id FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1',
            ['code' => 'en-GB']
        );

        $documentTypeName = $connection->fetchOne(
            'SELECT `name` FROM `document_type_translation` WHERE `document_type_id` = :documentTypeId AND `language_id` = :languageId',
            [
                'documentTypeId' => $cancellationId,
                'languageId' => $enLangId,
            ]
        );
        static::assertNotFalse($documentTypeName);

        $documentBaseConfig = $connection->fetchAssociative(
            'SELECT `name`, `filename_prefix` FROM `document_base_config` WHERE `document_type_id` = :documentTypeId',
            [
                'documentTypeId' => $cancellationId,
            ]
        );
        static::assertNotFalse($documentBaseConfig);

        static::assertEquals('Cancellation invoice', $documentTypeName);
        static::assertEquals('cancellation_invoice', $documentBaseConfig['name']);
        static::assertEquals('cancellation_invoice_', $documentBaseConfig['filename_prefix']);
    }
}
