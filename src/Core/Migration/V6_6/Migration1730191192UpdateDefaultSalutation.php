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
class Migration1730191192UpdateDefaultSalutation extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1730191192;
    }

    public function update(Connection $connection): void
    {
        $salutationId = $connection->fetchOne('SELECT id FROM salutation WHERE salutation_key = "not_specified"');

        if (!$salutationId) {
            return;
        }

        $this->updateSalutationForLanguage($connection, $salutationId, 'en-GB', 'Dear');
        $this->updateSalutationForLanguage($connection, $salutationId, 'de-DE', 'Guten Tag');
    }

    private function updateSalutationForLanguage(Connection $connection, string $salutationId, string $locale, string $letterName): void
    {
        $languageIds = $this->getLanguageIds($connection, $locale);
        if (!$languageIds) {
            return;
        }

        $this->updateSalutation($connection, $salutationId, $languageIds, $letterName);
    }

    /**
     * @param array<string> $languageIds
     */
    private function updateSalutation(Connection $connection, string $salutationId, array $languageIds, string $letterName): void
    {
        $connection->executeStatement('UPDATE salutation_translation SET letter_name = :letterName WHERE salutation_id = :salutationId AND language_id IN (:languageIds) AND updated_at IS NULL', [
            'letterName' => $letterName,
            'salutationId' => $salutationId,
            'languageIds' => Uuid::fromHexToBytesList($languageIds),
        ], ['languageIds' => ArrayParameterType::BINARY]);
    }
}
