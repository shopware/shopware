<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
readonly class DomainStruct
{
    public function __construct(
        public string $url,
        public string $id,
        public string $salesChannelId,
        public string $typeId,
        public string $snippetSetId,
        public string $currencyId,
        public string $languageId,
        public ?string $themeId,
        public string $maintenance,
        public ?string $maintenanceIpAllowlist,
        public string $locale,
        public ?string $themeName,
        public ?string $parentThemeName,
    ) {
    }

    /**
     * @param array<string, string|null> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            url: rtrim((string) $row['url'], '/'),
            id: (string) $row['id'],
            salesChannelId: (string) $row['salesChannelId'],
            typeId: (string) $row['typeId'],
            snippetSetId: (string) $row['snippetSetId'],
            currencyId: (string) $row['currencyId'],
            languageId: (string) $row['languageId'],
            themeId: isset($row['themeId']) ? (string) $row['themeId'] : null,
            maintenance: (string) $row['maintenance'],
            maintenanceIpAllowlist: isset($row['maintenanceIpAllowlist']) ? (string) $row['maintenanceIpAllowlist'] : null,
            locale: (string) $row['locale'],
            themeName: isset($row['themeName']) ? (string) $row['themeName'] : null,
            parentThemeName: isset($row['parentThemeName']) ? (string) $row['parentThemeName'] : null,
        );
    }
}
