<?php

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1701776000SetSystemDefaultForDefaultMailFooter extends MigrationStep
{
    use ImportTranslationsTrait;

    private const CHECKSUM_HTML_EN = '097ec7c0960cfff876856b1c4a2849b8';
    private const CHECKSUM_HTML_DE = '01bb8fbfc798ff789a1b8d97ed5df46c';
    private const CHECKSUM_PLAIN_EN = '6198b92aa8fbc81a9a91b7ea2ad19eaa';
    private const CHECKSUM_PLAIN_DE = 'b80230dec50b38ad9991656c476e0f4e';

    public function getCreationTimestamp(): int
    {
        return 1701776000;
    }

    public function update(Connection $connection): void
    {
        $languages = array_merge([Defaults::LANGUAGE_SYSTEM],
            $this->getLanguageIds($connection, 'en-GB'),
            $this->getLanguageIds($connection, 'de-DE'));
        $languages = array_unique(array_filter($languages));
        if (!$languages) {
            return;
        }

        $translations = $this->getTranslationIds($connection, $languages);
        if (empty($translations)) {
            return;
        }

        $mailHeaderFooterIds = [];
        $translationCount = count($translations);
        foreach ($translations as $translation) {
            // Check for default content in all translations
            if (
                (md5($translation['footer_plain']) === self::CHECKSUM_PLAIN_EN
                    && md5($translation['footer_html']) === self::CHECKSUM_HTML_EN)
                || (md5($translation['footer_plain']) === self::CHECKSUM_PLAIN_DE
                    && md5($translation['footer_html']) === self::CHECKSUM_HTML_DE)
            ) {
                $mailHeaderFooterIds[$translation['id']] = ($mailHeaderFooterIds[$translation['id']] ?? 0) + 1;
            }
        }

        // verify that no translation has been changed
        if (count($mailHeaderFooterIds) === 1 && reset($mailHeaderFooterIds) === $translationCount) {
            $connection->update(
                MailHeaderFooterDefinition::ENTITY_NAME,
                [
                    'system_default' => 1,
                ],
                [
                    'id' => key($mailHeaderFooterIds),
                ]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @param array<string> $languageIds
     *
     * @return mixed[]
     */
    private function getTranslationIds(Connection $connection, array $languageIds): array
    {
        return $connection->fetchAllAssociative(
            '
            SELECT mail_header_footer.id, mail_header_footer_translation.footer_plain, mail_header_footer_translation.footer_html

            FROM mail_header_footer

            INNER JOIN mail_header_footer_translation
                ON mail_header_footer.id = mail_header_footer_translation.mail_header_footer_id

            WHERE mail_header_footer_translation.language_id IN (:ids)
            AND mail_header_footer.system_default = 0
            AND mail_header_footer_translation.updated_at IS NULL
            AND mail_header_footer.updated_at IS NULL',
            ['ids' => Uuid::fromHexToBytesList($languageIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
