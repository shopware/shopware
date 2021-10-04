<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;

class LanguageLocaleProvider
{
    private Connection $connection;

    private array $locales = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getLocaleForLanguageId(string $languageId): string
    {
        $result = $this->getLocalesForLanguageIds([$languageId]);

        if (!\array_key_exists($languageId, $result)) {
            throw new LanguageNotFoundException($languageId);
        }

        return $result[$languageId];
    }

    public function getLocalesForLanguageIds(array $languageIds): array
    {
        if (\count(array_intersect_key($this->locales, array_flip($languageIds))) === \count($languageIds)) {
            return array_intersect_key($this->locales, array_flip($languageIds));
        }

        $locales = $this->fetchLocales($languageIds);

        $this->locales = array_merge($this->locales, $locales);

        return $locales;
    }

    private function fetchLocales(array $languageIds): array
    {
        return $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(language.id)), locale.code
            FROM language
            INNER JOIN locale ON locale.id = language.locale_id
            WHERE language.id in (:languageIds)',
            ['languageIds' => Uuid::fromHexToBytesList($languageIds)],
            ['languageIds' => Connection::PARAM_STR_ARRAY]
        );
    }
}
