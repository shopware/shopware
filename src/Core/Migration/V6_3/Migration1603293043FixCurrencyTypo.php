<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1603293043FixCurrencyTypo extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1603293043;
    }

    public function update(Connection $connection): void
    {
        $this->updateCurrency($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function updateCurrency(Connection $connection): void
    {
        try {
            $englishLanguageId = $connection->createQueryBuilder()
                ->select('lang.id')
                ->from('language', 'lang')
                ->innerJoin('lang', 'locale', 'loc', 'lang.translation_code_id = loc.id')
                ->where('loc.code = :englishLocale')
                ->setParameter('englishLocale', 'en-GB')
                ->executeQuery()
                ->fetchOne();

            if ($englishLanguageId === false) {
                return;
            }

            $enSwedishCurrencyTranslationUnchanged = $connection->createQueryBuilder()
                ->select('currency_id')
                ->from('currency_translation')
                ->where('language_id = :englishLocale AND short_name = :swedishKronaShortName AND updated_at IS NULL ')
                ->setParameters(['englishLocale' => $englishLanguageId, 'swedishKronaShortName' => 'SEK'])
                ->executeQuery()
                ->fetchOne();

            if ($enSwedishCurrencyTranslationUnchanged === false) {
                return;
            }
        } catch (\Exception) {
            return;
        }

        $currentDateTime = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $connection->update(
            'currency_translation',
            ['name' => 'Swedish krona', 'updated_at' => $currentDateTime],
            [
                'name' => 'Swedish krone',
                'language_id' => $englishLanguageId,
            ]
        );
    }
}
