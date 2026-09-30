<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Provider;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Log\Package;

/**
 * Offers a small, explicit allowlist of non-sensitive Storefront context values for data mapping.
 *
 * @internal
 */
#[Package('framework')]
final class StorefrontContextMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    private const SOURCE_ID = 'storefront';

    /**
     * @var array<string, string>
     */
    private const ALLOWED_PATHS = [
        'currency.isoCode' => 'sw-experience-studio.mapping.context.currencyIsoCode',
        'currency.symbol' => 'sw-experience-studio.mapping.context.currencySymbol',
        'language.localeCode' => 'sw-experience-studio.mapping.context.languageLocale',
        'tax.state' => 'sw-experience-studio.mapping.context.taxState',
    ];

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
        return $source->type === 'context' && $source->id === self::SOURCE_ID;
    }

    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): mixed
    {
        if ($context->salesChannelContext === null || !\array_key_exists((string) $source->path, self::ALLOWED_PATHS)) {
            return null;
        }

        return match ($source->path) {
            'currency.isoCode' => $context->salesChannelContext->getCurrency()->getIsoCode(),
            'currency.symbol' => $context->salesChannelContext->getCurrency()->getSymbol(),
            'language.localeCode' => $context->salesChannelContext->getLanguageInfo()->localeCode,
            'tax.state' => $context->salesChannelContext->getTaxState(),
            default => null,
        };
    }
}
