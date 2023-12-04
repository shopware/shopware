<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1701776095FixDefaultMailFooter extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1701776095;
    }

    public function update(Connection $connection): void
    {
        $plainDe = (string) file_get_contents(__DIR__ . '/../Fixtures/mails/defaultMailFooter/de-plain.twig');

        $languages = $this->getLanguageIds($connection, 'de-DE');
        if (!$languages) {
            return;
        }

        $translations = $this->getTranslationIds($connection, $languages);
        if (empty($translations)) {
            return;
        }

        foreach ($translations as $translation) {
            $connection->update(
                MailHeaderFooterTranslationDefinition::ENTITY_NAME,
                [
                    'footer_plain' => $plainDe,
                ],
                [
                    'language_id' => $translation['language_id'],
                    'mail_header_footer_id' => $translation['mail_header_footer_id'],
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
            SELECT mail_header_footer_translation.mail_header_footer_id, mail_header_footer_translation.language_id

            FROM mail_header_footer

            INNER JOIN mail_header_footer_translation
                ON mail_header_footer.id = mail_header_footer_translation.mail_header_footer_id

            WHERE mail_header_footer_translation.language_id IN (:ids)
            AND mail_header_footer.system_default = 1
            AND mail_header_footer_translation.updated_at IS NULL
            AND mail_header_footer.updated_at IS NULL',
            ['ids' => Uuid::fromHexToBytesList($languageIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
