<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Provider;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext\StorefrontContextLoader;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * Offers a small, explicit allowlist of non-sensitive Storefront context values for data mapping.
 *
 * @internal
 */
#[Package('framework')]
final class StorefrontContextMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    public const SOURCE_ID = 'storefront';

    /**
     * @var array<string, string>
     */
    private const ALLOWED_PATHS = [
        'currency.isoCode' => 'sw-experience-studio.mapping.context.currencyIsoCode',
        'currency.symbol' => 'sw-experience-studio.mapping.context.currencySymbol',
        'language.localeCode' => 'sw-experience-studio.mapping.context.languageLocale',
        'tax.state' => 'sw-experience-studio.mapping.context.taxState',
    ];

    public static function registeredCandidateKeys(): array
    {
        return [
            '*' => array_map(
                static fn (string $path): string => 'context:' . self::SOURCE_ID . '.' . $path,
                array_keys(self::ALLOWED_PATHS),
            ),
        ];
    }

    public function supports(string $rootSource): bool
    {
        return $rootSource !== '';
    }

    public function provide(string $rootSource): array
    {
        $candidates = [];

        foreach (self::ALLOWED_PATHS as $path => $label) {
            $source = new MappingSourceReference('context', self::SOURCE_ID, path: $path);
            $name = $source->displayName();
            $candidates[] = new MappingCandidate(
                path: $name,
                label: $label . '.label',
                description: $label . '.description',
                group: 'storefront-context',
                valueType: 'string',
                contextType: ContextType::Single,
                source: $source,
            );
        }

        return $candidates;
    }

    public function supportsSource(MappingSourceReference $source): bool
    {
        return self::isApprovedSource($source);
    }

    public static function isApprovedSource(MappingSourceReference $source): bool
    {
        return $source->type === 'context'
            && $source->id === self::SOURCE_ID
            && $source->config === []
            && $source->path !== null
            && \array_key_exists($source->path, self::ALLOWED_PATHS);
    }

    public static function isApprovedDisplayName(string $path): bool
    {
        $prefix = 'context:' . self::SOURCE_ID . '.';

        return str_starts_with($path, $prefix) && \array_key_exists(substr($path, \strlen($prefix)), self::ALLOWED_PATHS);
    }

    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        if (!self::isApprovedSource($source)) {
            return MappingSourceResolution::noValue();
        }

        $loaded = $context->loaderValues[StorefrontContextLoader::REQUIREMENT_KEY] ?? null;
        if (!$loaded instanceof ArrayStruct) {
            return MappingSourceResolution::noValue();
        }

        $values = $loaded->getVars();
        $path = $source->path;
        if ($path === null) {
            return MappingSourceResolution::noValue();
        }

        return \array_key_exists($path, $values)
            ? MappingSourceResolution::resolved($values[$path])
            : MappingSourceResolution::noValue();
    }
}
