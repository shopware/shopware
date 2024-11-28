<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;

#[Package('storefront')]
class ThemeRuntimeConfig
{
    public function __construct(
        public readonly string $themeId,
        public readonly array $resolvedConfig,
        public readonly array $scriptFiles,
        public readonly array $iconSets,
        public readonly \DateTimeInterface $updatedAt
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['themeId'],
            $data['resolvedConfig'],
            $data['scriptFiles'],
            $data['iconSets'],
            $data['updatedAt'],
        );
    }
}
