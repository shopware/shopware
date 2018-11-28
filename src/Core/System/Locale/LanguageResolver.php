<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Doctrine\DBAL\Connection;
use Shopware\Core\System\Exception\LanguageIdentifierNotFoundException;

class LanguageResolver implements LanguageResolverInterface
{
    /**
     * @var array|null
     */
    private $languages;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getRootLanguageIds(): array
    {
        return \array_map(
            function ($lang) { return $lang['id']; },
            \array_filter($this->getLanguages(), function ($lang) { return $lang['isRoot']; })
        );
    }

    public function isRootLanguage(string $identifier): bool
    {
        $languages = $this->getLanguages();
        if (!isset($languages[$identifier])) {
            throw new LanguageIdentifierNotFoundException($identifier);
        }

        return $languages['isRoot'];
    }

    public function getLanguageIdByIdentifier(string $identifier): string
    {
        $languages = $this->getLanguages();
        if (!isset($languages[$identifier])) {
            throw new LanguageIdentifierNotFoundException($identifier);
        }

        return $languages[$identifier]['id'];
    }

    private function getLanguages(): array
    {
        if ($this->languages !== null) {
            return $this->languages;
        }

        $data = $this->connection->createQueryBuilder()
            ->select(['LOWER(HEX(language.id)) AS id, locale.code, (language.parent_id IS NULL) isRoot'])
            ->from('language')
            ->leftJoin('language', 'locale', 'locale', 'language.translation_code_id = locale.id')
            ->execute()
            ->fetchAll();

        $languages = [];
        foreach ($data as $row) {
            $row['isRoot'] = (bool) $row['isRoot'];
            $languages[$row['id']] = $row;
            if ($row['code']) {
                $languages[$row['code']] = $row;
            }
        }

        return $this->languages = $languages;
    }
}
