<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1597255720AddRawTwigFilterInPlainMailTemplateFields extends MigrationStep
{
    public const UP = <<<'SQL'
UPDATE mail_template_translation
INNER JOIN mail_template ON mail_template_translation.mail_template_id = mail_template.id
SET mail_template_translation.sender_name = REPLACE(mail_template_translation.sender_name, '}}', '|raw }}'),
    mail_template_translation.subject = REPLACE(mail_template_translation.subject, '}}', '|raw }}'),
    mail_template_translation.content_plain = REPLACE(mail_template_translation.content_plain, '}}', '|raw }}')
WHERE mail_template.system_default = 1
SQL;

    public function getCreationTimestamp(): int
    {
        return 1597255720;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(self::UP);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
