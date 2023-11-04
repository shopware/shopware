<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1598885384RedirectToLatestSeoUrl extends MigrationStep
{
    final public const CONFIG_KEY = 'core.seo.redirectToCanonicalUrl';

    public function getCreationTimestamp(): int
    {
        return 1598885384;
    }

    public function update(Connection $connection): void
    {
        if ($this->configPresent($connection)) {
            return;
        }

        /*
         * When there are SEO-URLs, the system did already go through the
         * installation process, and the current behaviour shouldn't change.
         * Therefore the configuration option is inserted but inactive in this
         * case.
         */
        $this->insertConfig(
            $connection,
            !$this->seoUrlPresent($connection)
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function configPresent(Connection $connection): bool
    {
        return $connection->fetchOne(
            'SELECT `id` FROM `system_config` WHERE `configuration_key` = :config_key LIMIT 1;',
            ['config_key' => self::CONFIG_KEY]
        ) !== false;
    }

    private function insertConfig(Connection $connection, bool $isActiveByDefault): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => sprintf('{"_value": %s}', $isActiveByDefault ? 'true' : 'false'),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function seoUrlPresent(Connection $connection): bool
    {
        return $connection->fetchOne('SELECT `id` FROM `seo_url` LIMIT 1;') !== false;
    }
}
