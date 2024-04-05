<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1711461579FixDefaultMailFooter extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1711461579;
    }

    public function update(Connection $connection): void
    {
        $languages = $this->getLanguageIds($connection, 'de-DE');
        if (!$languages) {
            return;
        }

        $connection->executeStatement(
            'UPDATE mail_header_footer_translation
            SET footer_plain = REPLACE(footer_plain, \'Addresse:\', \'Adresse:\')
            WHERE language_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($languages)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
