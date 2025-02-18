<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1739916340SenderNameRequired extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1739916340;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE mail_template_translation
            SET sender_name = :defaultSenderName
            WHERE sender_name IS NULL OR sender_name = ""',
            ['defaultSenderName' => '{{ salesChannel.name }}']
        );
    }
}
