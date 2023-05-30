<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1575197543MailTemplateCustomFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1575197543;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `mail_template_translation`
ADD `custom_fields` json NULL AFTER `content_plain`;'
        );

        $connection->executeStatement(
            'ALTER TABLE `mail_template_translation` ADD CONSTRAINT `json.mail_template_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`));'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
