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
 *
 * @phpstan-type ThemeRuntimeConfigArray array{
 *     themeId: string,
 *     technicalName: ?string,
 *     resolvedConfig?: array<string, mixed>,
 *     viewInheritance?: array<string>,
 *     scriptFiles?: array<string>|null,
 *     iconSets?: array<string, array{path: string, namespace: string}>,
 *     componentImportMap?: array{imports: array<string, string>, scopes?: array<string, array<string, string>>}|null,
 *     updatedAt?: \DateTimeInterface|null
 * }
 * @phpstan-type ThemeRuntimeConfigArrayOverrides array{
 *     themeId?: string,
 *     technicalName?: string|null,
 *     resolvedConfig?: array<string, mixed>,
 *     viewInheritance?: array<string>,
 *     scriptFiles?: array<string>|null,
 *     iconSets?: array<string, array{path: string, namespace: string}>,
 *     componentImportMap?: array{imports: array<string, string>, scopes?: array<string, array<string, string>>}|null,
 *     updatedAt?: \DateTimeInterface|null
 * }
 */
#[Package('framework')]
class ThemeRuntimeConfig
{
    public function __construct(
        public readonly string $themeId,
        public readonly ?string $technicalName,
        /**
         * @var array<string, mixed>
         */
        public readonly array $resolvedConfig,
        /**
         * @var array<string>
         */
        public readonly array $viewInheritance,
        /**
         * @var array<string>|null
         */
        public readonly ?array $scriptFiles,
        /**
         * @var array<string, array{path: string, namespace: string}>
         */
        public readonly array $iconSets,
        public readonly \DateTimeInterface $updatedAt,
        /**
         * Pre-built component import map stored as theme-relative paths.
         * Built once by ThemeCompiler::buildComponentImportMap() at compile time and stored here
         * so that TemplateConfigAccessor can convert paths to full URLs at request time without
         * re-reading any build artefacts.
         *
         * @var array{imports: array<string, string>, scopes?: array<string, array<string, string>>}|null
         */
        public readonly ?array $componentImportMap = null,
    ) {
    }

    /**
     * @param ThemeRuntimeConfigArray $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['themeId'],
            $data['technicalName'],
            $data['resolvedConfig'] ?? [],
            $data['viewInheritance'] ?? [],
            $data['scriptFiles'] ?? null,
            $data['iconSets'] ?? [],
            $data['updatedAt'] ?? new \DateTimeImmutable(),
            $data['componentImportMap'] ?? null,
        );
    }

    /**
     * Creates a new ThemeRuntimeConfig with the specified fields updated.
     *
     * @param ThemeRuntimeConfigArrayOverrides $data
     */
    public function with(array $data): self
    {
        return new self(
            $data['themeId'] ?? $this->themeId,
            \array_key_exists('technicalName', $data) ? $data['technicalName'] : $this->technicalName,
            $data['resolvedConfig'] ?? $this->resolvedConfig,
            $data['viewInheritance'] ?? $this->viewInheritance,
            \array_key_exists('scriptFiles', $data) ? $data['scriptFiles'] : $this->scriptFiles,
            $data['iconSets'] ?? $this->iconSets,
            $data['updatedAt'] ?? $this->updatedAt,
            \array_key_exists('componentImportMap', $data) ? $data['componentImportMap'] : $this->componentImportMap,
        );
    }
}
