<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1768545322AssignRevocationPageToSystemConfigSetting extends MigrationStep
{
    final public const REVOCATION_PAGE_CONFIG_KEY = 'core.basicInformation.revocationRequestPage';
    final public const REVOCATION_BUTTON_CONFIG_KEY = 'core.basicInformation.showRevocationButton';

    public function getCreationTimestamp(): int
    {
        return 1768545322;
    }

    public function update(Connection $connection): void
    {
        if ($this->isPageAssigned($connection)) {
            return;
        }

        $pageByteId = $this->getPageId($connection);
        if ($pageByteId === null) {
            return;
        }

        $this->linkPage($connection, $pageByteId);
        $this->disableButton($connection);
    }

    private function disableButton(Connection $connection): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::REVOCATION_BUTTON_CONFIG_KEY,
            'configuration_value' => '{"_value": false}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function linkPage(Connection $connection, string $pageByteId): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::REVOCATION_PAGE_CONFIG_KEY,
            'configuration_value' => \sprintf('{"_value": "%s"}', Uuid::fromBytesToHex($pageByteId)),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getPageId(Connection $connection): ?string
    {
        return $connection->fetchOne(
            'SELECT cms_page_id FROM cms_page_translation WHERE name = :name',
            ['name' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name']]
        );
    }

    private function isPageAssigned(Connection $connection): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM system_config WHERE configuration_key = :configKey LIMIT 1',
            ['configKey' => self::REVOCATION_PAGE_CONFIG_KEY]
        );
    }
}
