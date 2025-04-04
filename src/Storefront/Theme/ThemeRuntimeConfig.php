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
#[Package('framework')]
class ThemeRuntimeConfig
{
    public function __construct(
        public readonly string $themeId,
        public readonly string $technicalName,
        /** @var array<mixed> */
        public readonly array $resolvedConfig,
        /** @var array<string> */
        public readonly array $viewInheritance,
        /** @var array<string>|null */
        public readonly ?array $scriptFiles,
        /** @var array<string, array{path: string, namespace: string}> */
        public readonly array $iconSets,
        public readonly \DateTimeInterface $updatedAt
    ) {
    }

    /**
     * @param array{
     *     themeId: string,
     *     technicalName: string,
     *     resolvedConfig?: array<mixed>,
     *     viewInheritance?: array<string>,
     *     scriptFiles?: array<string>|null,
     *     iconSets?: array<string, array{path: string, namespace: string}>,
     *     updatedAt?: \DateTimeInterface
     * } $data
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
        );
    }
}
