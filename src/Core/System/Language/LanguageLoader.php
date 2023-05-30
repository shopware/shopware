<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type LanguageData from LanguageLoaderInterface
 */
#[Package('core')]
class LanguageLoader implements LanguageLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return LanguageData
     */
    public function loadLanguages(): array
    {
        $data = $this->connection->createQueryBuilder()
            ->select(['LOWER(HEX(language.id)) AS array_key, LOWER(HEX(language.id)) AS id, locale.code, LOWER(HEX(language.parent_id)) parentId'])
            ->from('language')
            ->leftJoin('language', 'locale', 'locale', 'language.translation_code_id = locale.id')
            ->executeQuery()
            ->fetchAllAssociative();

        return FetchModeHelper::groupUnique($data);
    }
}
