<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

trait ImportTranslationsTrait
{
    protected function importTranslation(string $table, Translations $translations, Connection $connection): TranslationWriteResult
    {
        $germanIds = $this->getLanguageIds($connection, 'de-DE');
        $englishIds = array_diff(
            array_merge($this->getLanguageIds($connection, 'en-GB'), [Defaults::LANGUAGE_SYSTEM]),
            $germanIds
        );

        $columns = [];
        $values = [];

        $keys = array_merge($translations->getColumns(), ['created_at', 'language_id']);
        foreach ($keys as $column) {
            $columns[] = '`' . $column . '`';
            $values[] = ':' . $column;
        }

        $sql = str_replace(
            ['#columns#', '#values#', '#table#'],
            [
                implode(',', $columns),
                implode(',', $values),
                '`' . $table . '`',
            ],
            'REPLACE INTO #table# (#columns#) VALUES (#values#)'
        );

        foreach ($englishIds as $id) {
            $data = array_merge($translations->getEnglish(), [
                'language_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            $connection->executeUpdate($sql, $data);
        }

        foreach ($germanIds as $id) {
            $data = array_merge($translations->getGerman(), [
                'language_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            $connection->executeUpdate($sql, $data);
        }

        return new TranslationWriteResult($englishIds, $germanIds);
    }

    protected function getLanguageIds(Connection $connection, string $locale): array
    {
        $ids = $connection->fetchAll('
            SELECT LOWER(HEX(`language`.id)) as id
            FROM `language`
            INNER JOIN locale
                ON `language`.`locale_id` = `locale`.`id`
                AND locale.code = :locale
        ', ['locale' => $locale]);

        return array_unique(array_filter(array_column($ids, 'id')));
    }
}
