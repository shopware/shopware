<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('core')]
class SalesChannelLanguageLoader implements ResetInterface
{
    /**
     * @var array<string, array<string>>|null
     */
    private ?array $languages = null;

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, array<string>>
     */
    public function loadLanguages(): array
    {
        if ($this->languages) {
            return $this->languages;
        }

        $result = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(`language_id`)), LOWER(HEX(`sales_channel_id`)) as salesChannelId FROM sales_channel_language');

        /** @var array<string, array{ salesChannelId: string }> $grouped */
        $grouped = FetchModeHelper::group($result);

        foreach ($grouped as $languageId => $value) {
            $grouped[$languageId] = array_column($value, 'salesChannelId');
        }

        return $this->languages = $grouped;
    }

    public function reset(): void
    {
        $this->languages = null;
    }
}
