<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

class Migration1619428555AddDefaultMailFooter extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1619428555;
    }

    public function update(Connection $connection): void
    {
        $id = Uuid::randomBytes();

        $connection->insert(MailHeaderFooterDefinition::ENTITY_NAME, [
            'id' => $id,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $translations = new Translations(
            [
                'mail_header_footer_id' => $id,
                'name' => 'Standard-E-Mail-Fußzeile',
                'description' => 'Standard-E-Mail-Fußzeile basierend auf den Stammdaten',
                'header_html' => null,
                'header_plain' => null,
                'footer_plain' => (string) \file_get_contents(__DIR__ . '/../Fixtures/mails/defaultMailFooter/de-plain.twig'),
                'footer_html' => (string) \file_get_contents(__DIR__ . '/../Fixtures/mails/defaultMailFooter/de-html.twig'),
            ],
            [
                'mail_header_footer_id' => $id,
                'name' => 'Default email footer',
                'description' => 'Default email footer derived from basic information',
                'header_html' => null,
                'header_plain' => null,
                'footer_plain' => (string) \file_get_contents(__DIR__ . '/../Fixtures/mails/defaultMailFooter/en-plain.twig'),
                'footer_html' => (string) \file_get_contents(__DIR__ . '/../Fixtures/mails/defaultMailFooter/en-html.twig'),
            ]
        );

        $this->importTranslation(MailHeaderFooterTranslationDefinition::ENTITY_NAME, $translations, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
