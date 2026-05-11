<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Removes seeded translation rows for system_default mail templates on fresh installations only.
 *
 * The 31 legacy migrations under V6_3..V6_7 that insert into `mail_template_translation` still run during
 * install for backwards-compatibility (upgrades replay the full migration history). For a fresh install the
 * resulting rows duplicate what {@see \Shopware\Core\Content\MailTemplate\Defaults\MailTemplateDefaultsRegistry}
 * already serves at read time — so we delete them here, leaving the table empty until a merchant explicitly
 * overrides a field.
 *
 * Upgrades (`isInstallation() === false`) leave existing rows alone; they may contain merchant edits.
 *
 * @internal
 */
#[Package('after-sales')]
class Migration1778514014ClearSystemMailTemplateTranslations extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1778514014;
    }

    public function update(Connection $connection): void
    {
        if (!$this->isInstallation()) {
            return;
        }

        $connection->executeStatement('
            DELETE tr FROM `mail_template_translation` tr
            INNER JOIN `mail_template` t ON t.id = tr.mail_template_id
            WHERE t.system_default = 1
        ');
    }
}
