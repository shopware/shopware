<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1551793240MailTemplateMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551793240;
    }

    public function update(Connection $connection): void
    {
        $query = <<<SQL
CREATE TABLE mail_template_media (
  id BINARY(16) NOT NULL,
  mail_template_id BINARY(16) NOT NULL,
  media_id BINARY(16) NOT NULL,
  position INT(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT `fk.mail_template_attachment.mail_template_id` FOREIGN KEY (`mail_template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.mail_template_attachment.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)
SQL;

        $connection->exec($query);

        $connection->insert('media_default_folder', [
            'id' => Uuid::randomBytes(),
            'association_fields' => '["mailTemplateMedia"]',
            'entity' => 'mail_template',
            'thumbnail_sizes' => '[]',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
