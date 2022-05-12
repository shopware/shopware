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
            ->select(['LOWER(HEX(language.id)) AS array_key, LOWER(HEX(language.id)) AS id, IFNULL(locale.code, parentLocale.code) as code, LOWER(HEX(language.parent_id)) parentId'])
            ->from('language')
            ->leftJoin('language', 'locale', 'locale', 'language.translation_code_id = locale.id')
            ->leftJoin('language', 'language', 'parent', 'language.parent_id = parent.id')
            ->leftJoin('language', 'locale', 'parentLocale', 'parent.translation_code_id = parentLocale.id')
            ->execute()
            ->fetchAll();

        return FetchModeHelper::groupUnique($data);
    }
}
