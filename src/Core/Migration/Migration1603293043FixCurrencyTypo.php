<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

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
            /** var Doctrine\DBAL\Driver\Statement  $englishLanguageId  */
            $englishLanguageId = $connection->createQueryBuilder()
                ->select('lang.id')
                ->from('language', 'lang')
                ->innerJoin('lang', 'locale', 'loc', 'lang.translation_code_id = loc.id')
                ->where('loc.code = :englishLocale')
                ->setParameter(':englishLocale', 'en-GB')
                ->execute();

            if ($englishLanguageId && !\is_int($englishLanguageId)) {
                $englishLanguageId = $englishLanguageId->fetchColumn();
            }
            if (!$englishLanguageId) {
                return;
            }

            /** var Doctrine\DBAL\Driver\Statement  $enSwedishCurrencyTranslationUnchanged  */
            $enSwedishCurrencyTranslationUnchanged = $connection->createQueryBuilder()
                ->select('currency_id')
                ->from('currency_translation')
                ->where('language_id = :englishLocale AND short_name = :swedishKronaShortName AND updated_at IS NULL ')
                ->setParameters([':englishLocale' => $englishLanguageId, ':swedishKronaShortName' => 'SEK'])
                ->execute();
            if ($enSwedishCurrencyTranslationUnchanged && !\is_int($enSwedishCurrencyTranslationUnchanged)) {
                $enSwedishCurrencyTranslationUnchanged = $enSwedishCurrencyTranslationUnchanged->fetchColumn();
            }

            if (!$enSwedishCurrencyTranslationUnchanged) {
                return;
            }
        } catch (\Exception $e) {
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
