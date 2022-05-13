<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;

class LanguageLoader implements LanguageLoaderInterface
{
    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function loadLanguages(): array
    {
        $data = $this->connection->createQueryBuilder()
            ->select(['LOWER(HEX(language.id)) AS array_key, LOWER(HEX(language.id)) AS id, locale.code, LOWER(HEX(language.parent_id)) parentId'])
            ->from('language')
            ->leftJoin('language', 'locale', 'locale', 'language.translation_code_id = locale.id')
            ->execute()
            ->fetchAll();

        $languages = FetchModeHelper::groupUnique($data);

        return $this->resolveLocaleCodes($languages);
    }

    private function resolveLocaleCodes(array $languages): array
    {
        foreach ($languages as $languageId => $language) {
            if ($language['code'] !== null) {
                continue;
            }

            $currentLanguageId = $languageId;
            while ($language['code'] === null) {
                $currentLanguageId = $languages[$currentLanguageId]['parentId'];

                $language['code'] = $languages[$currentLanguageId]['code'];
            }

            $languages[$languageId]['code'] = $language['code'];
        }

        return $languages;
    }
}
