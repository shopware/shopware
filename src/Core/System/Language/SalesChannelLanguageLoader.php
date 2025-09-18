<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * @phpstan-import-type LanguageData from LanguageLoaderInterface
 */
#[Package('fundamentals@discovery')]
class SalesChannelLanguageLoader implements ResetInterface
{
    /**
     * @var LanguageData|null
     */
    private ?array $languages = null;

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
        if ($this->languages !== null) {
            return $this->languages;
        }

        $result = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(`language_id`)), LOWER(HEX(`sales_channel_id`)) as salesChannelId FROM sales_channel_language');

        $grouped = FetchModeHelper::group($result);

        foreach ($grouped as $languageId => $value) {
            $grouped[$languageId] = array_column($value, 'salesChannelId');
        }

        /** @var LanguageData $grouped */
        // @phpstan-ignore varTag.type (phpstan can't correctly detect the array_column usage here)
        return $this->languages = $grouped;
    }

    public function reset(): void
    {
        $this->languages = null;
    }
}
