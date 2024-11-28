<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * DTO to store precalculated configuration of a theme used during storefront rendering.
 * Used to avoid recalculating the configuration for every request.
 *
 * Most of the properties are calculated during Shopware\Storefront\Theme\ThemeLifecycleService::refreshTheme.
 * The $scriptFiles are calculated just after Shopware\Storefront\Theme\ThemeCompiler::compileTheme.
 */
#[Package('storefront')]
class ThemeRuntimeConfig
{
    public function __construct(
        public readonly string $themeId,
        public readonly string $technicalName,
        public readonly array $resolvedConfig,
        public readonly ?array $scriptFiles,
        public readonly array $iconSets,
        public readonly \DateTimeInterface $updatedAt
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['themeId'],
            $data['technicalName'],
            $data['resolvedConfig'],
            $data['scriptFiles'] ?? null,
            $data['iconSets'],
            $data['updatedAt'],
        );
    }
}
