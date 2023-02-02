<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Struct\Struct;

class ThemeSalesChannel extends Struct
{
    protected string $themeId;

    protected string $salesChannelId;

    public function __construct(string $themeId, string $salesChannelId)
    {
        $this->themeId = $themeId;
        $this->salesChannelId = $salesChannelId;
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }

    public function setThemeId(string $themeId): void
    {
        $this->themeId = $themeId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }
}
