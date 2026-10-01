<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
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

    /**
     * @return List<array<string, mixed>>
     */
    private function getMailTemplateTranslations(Connection $connection): array
    {
        $sql = <<<SQL
SELECT
    mtt.mail_template_id,
    mtt.language_id,
    mtt.sender_name,
    mtt.subject,
    mtt.content_html,
    mtt.content_plain
FROM mail_template_translation AS mtt
INNER JOIN mail_template AS mt ON mt.id = mtt.mail_template_id
WHERE mt.system_default = 1
SQL;

        return $connection->fetchAllAssociative($sql);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function updateMailTemplateTranslationRow(array $row, Connection $connection): void
    {
        $sql = <<<SQL
UPDATE mail_template_translation 
SET sender_name = :sender_name, 
    subject = :subject, 
    content_html = :content_html, 
    content_plain = :content_plain
WHERE mail_template_id = :mail_template_id 
  AND language_id = :language_id
SQL;

        $connection->executeStatement($sql, $row);
    }
}
