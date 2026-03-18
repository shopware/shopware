<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1773815272SalesChannelNameTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773815272;
    }

    public function update(Connection $connection): void
    {
        $this->updateMailTemplateTranslationTable($connection);
//        $this->updateMailHeaderFooterTranslation($connection);
    }

    private function replaceSalesChannelNameTag(?string $content): ?string
    {
        if ($content === null) {
            return $content;
        }

        $search = '{{ salesChannel.name }}';
        $replace = '{{ salesChannel.translated.name }}';

        return str_replace(
            $search,
            $replace,
            $content,
        );
    }

    private function updateMailTemplateTranslationTable(Connection $connection): void
    {
        $result = $this->getMailTemplateTranslations($connection);

        foreach ($result as &$row) {
            $row['sender_name'] = $this->replaceSalesChannelNameTag($row['sender_name']);
            $row['subject'] = $this->replaceSalesChannelNameTag($row['subject']);
            $row['content_html'] = $this->replaceSalesChannelNameTag($row['content_html']);
            $row['content_plain'] = $this->replaceSalesChannelNameTag($row['content_plain']);

            $this->updateMailTemplateTranslationRow($row, $connection);
        }
    }

    private function updateMailHeaderFooterTranslation(Connection $connection): void
    {
        $result = $this->getMailHeaderFooterTranslation($connection);
        foreach ($result as &$row) {
            $row['header_html'] = $this->replaceSalesChannelNameTag($row['header_html']);
            $row['header_plain'] = $this->replaceSalesChannelNameTag($row['header_plain']);
            $row['footer_html'] = $this->replaceSalesChannelNameTag($row['footer_html']);
            $row['footer_plain'] = $this->replaceSalesChannelNameTag($row['footer_plain']);

            $this->updateMailHeaderFooterTranslationRow($row, $connection);
        }
    }

    /**
     * @return List<array{mail_template_id: string, language_id: string, sender_name: string, subject: string, content_html: string, content_plain: string}>
     */
    private function getMailTemplateTranslations(Connection $connection): array
    {
        $sql = <<<SQL
SELECT id 
FROM mail_template 
WHERE system_default = 1;
SQL;
        $mailTemplateIds = $connection->fetchFirstColumn($sql);

        $sql = <<<SQL
SELECT mail_template_id, language_id, sender_name, subject, content_html, content_plain
FROM mail_template_translation
WHERE mail_template_id IN (:mailTemplateIds)
SQL;

        return $connection->fetchAllAssociative($sql, ['mailTemplateIds' => $mailTemplateIds], ['mailTemplateIds' => ArrayParameterType::BINARY]);
    }

    /**
     * @param array{mail_template_id: string, language_id: string, sender_name: string, subject: string, content_html: string, content_plain: string} $row
     */
    private function updateMailTemplateTranslationRow(array $row, Connection $connection): void
    {
        $row['updated_at'] = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $sql = <<<SQL
UPDATE mail_template_translation 
SET sender_name = :sender_name, 
    subject = :subject, 
    content_html = :content_html, 
    content_plain = :content_plain,
    updated_at = :updated_at
WHERE mail_template_id = :mail_template_id 
  AND language_id = :language_id
SQL;

        $connection->executeStatement($sql, $row);
    }

    /**
     * @return List<array{mail_header_footer_id: string, header_html: string, header_plain: string, footer_html: string, footer_plain: string}>
     */
    private function getMailHeaderFooterTranslation(Connection $connection): array
    {
        $sql = <<<SQL
SELECT mail_header_footer_id, header_html, header_plain, footer_html, footer_plain 
FROM mail_header_footer_translation
SQL;

        return $connection->fetchAllAssociative($sql);
    }

    /**
     * @param array{mail_header_footer_id: string, header_html: string, header_plain: string, footer_html: string, footer_plain: string} $row
     */
    private function updateMailHeaderFooterTranslationRow(array $row, Connection $connection): void
    {
        $sql = <<<SQL
UPDATE mail_header_footer_translation 
SET header_html = :header_html, 
    header_plain = :header_plain, 
    footer_html = :footer_html, 
    footer_plain = :footer_plain
WHERE mail_header_footer_id = :mail_header_footer_id
SQL;

        $connection->executeStatement($sql, $row);
    }
}
