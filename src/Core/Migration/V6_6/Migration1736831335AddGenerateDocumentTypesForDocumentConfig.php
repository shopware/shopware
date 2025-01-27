<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1736831335AddGenerateDocumentTypesForDocumentConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1736831335;
    }

    /**
     * @throws \Throwable
     */
    public function update(Connection $connection): void
    {
        $connection->transactional(function (Connection $transaction): void {
            $documentConfig = $transaction->executeQuery(
                <<<SQL
                    SELECT `document_base_config`.`id`, `document_base_config`.`config` FROM `document_base_config`
                    JOIN `document_type` ON `document_base_config`.`document_type_id` = `document_type`.`id`
                    WHERE `document_type`.`technical_name` IN (:technicalName);
                    SQL,
                ['technicalName' => [InvoiceRenderer::TYPE, CreditNoteRenderer::TYPE, StornoRenderer::TYPE, DeliveryNoteRenderer::TYPE]],
                ['technicalName' => ArrayParameterType::STRING],
            )->fetchAllAssociative();

            if (empty($documentConfig)) {
                return;
            }

            foreach ($documentConfig as $config) {
                $id = $config['id'];
                $config = json_decode($config['config'], true, 512, \JSON_THROW_ON_ERROR);

                if (!isset($config['fileTypes'])) {
                    $config['fileTypes'] = [HtmlRenderer::FILE_EXTENSION, PdfRenderer::FILE_EXTENSION];
                }

                $transaction->executeQuery(
                    'UPDATE `document_base_config` SET `config` = :config WHERE `id` = :id;',
                    [
                        'id' => $id,
                        'config' => json_encode($config, \JSON_THROW_ON_ERROR),
                    ],
                );
            }
        });
    }
}
