<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1603293043FixCurrencyTypo extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1603293043;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->updateCurrency($connection);
    }

    private function updateCurrency(Connection $connection): void
    {
        $englishLanguageId = $connection->createQueryBuilder()
            ->select('lang.id')
            ->from('language', 'lang')
            ->innerJoin('lang', 'locale', 'loc', 'lang.translation_code_id = loc.id')
            ->where('loc.code = :englishLocale')
            ->setParameter(':englishLocale', 'en-GB')
            ->execute()
            ->fetchColumn();

        if (!$englishLanguageId) {
            return;
        }

        $connection->update(
            'currency_translation',
            ['name' => 'Swedish krona'],
            [
                'name' => 'Swedish krone',
                'language_id' => $englishLanguageId,
            ]
        );
    }
}
