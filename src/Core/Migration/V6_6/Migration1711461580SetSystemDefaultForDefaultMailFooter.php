<?php

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1711461580SetSystemDefaultForDefaultMailFooter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1711461580;
    }

    public function update(Connection $connection): void
    {
        $germanHtml = file_get_contents(__DIR__ . '/../Fixtures/mails/defaultMailFooter/de-html.twig');
        $germanPlain = file_get_contents(__DIR__ . '/../Fixtures/mails/defaultMailFooter/de-plain.twig');

        // Check if a template contains the German default content.
        // The ID of the oldest one will be returned as this is the one, created during the installation.
        $mailFooterIdGermanCheck = $connection->fetchOne(
            'SELECT id FROM mail_header_footer
             INNER JOIN mail_header_footer_translation
                ON mail_header_footer.id = mail_header_footer_translation.mail_header_footer_id
             WHERE mail_header_footer_translation.footer_html = :germanHtml
             AND mail_header_footer_translation.footer_plain = :germanPlain
             ORDER BY mail_header_footer_translation.created_at ASC
             LIMIT 1',
            [
                'germanHtml' => $germanHtml,
                'germanPlain' => $germanPlain,
            ]
        );

        // If no template with the German default content exists, we don't need to set the system default.
        if (!\is_string($mailFooterIdGermanCheck)) {
            return;
        }

        $englishHtml = file_get_contents(__DIR__ . '/../Fixtures/mails/defaultMailFooter/en-html.twig');
        $englishPlain = file_get_contents(__DIR__ . '/../Fixtures/mails/defaultMailFooter/en-plain.twig');

        // Check if a template contains the English default content.
        // The ID of the oldest one will be returned as this is the one, created during the installation.
        $mailFooterIdEnglishCheck = $connection->fetchOne(
            'SELECT id FROM mail_header_footer
             INNER JOIN mail_header_footer_translation
                ON mail_header_footer.id = mail_header_footer_translation.mail_header_footer_id
             WHERE mail_header_footer_translation.footer_html = :englishHtml
             AND mail_header_footer_translation.footer_plain = :englishPlain
             ORDER BY mail_header_footer_translation.created_at ASC
             LIMIT 1',
            [
                'englishHtml' => $englishHtml,
                'englishPlain' => $englishPlain,
            ]
        );

        // If no template with the English default content exists, we don't need to set the system default.
        if (!\is_string($mailFooterIdEnglishCheck)) {
            return;
        }

        // If both checks are returning the same ID, we can set this template as the system default.
        if ($mailFooterIdGermanCheck === $mailFooterIdEnglishCheck) {
            $connection->update(
                MailHeaderFooterDefinition::ENTITY_NAME,
                ['system_default' => 1],
                ['id' => $mailFooterIdGermanCheck]
            );
        }
    }
}
