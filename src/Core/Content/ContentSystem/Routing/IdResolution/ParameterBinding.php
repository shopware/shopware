<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class ParameterBinding
{
    /**
     * @param string $parameterName From route pattern (e.g., 'seoUrl' in /product/{seoUrl})
     * @param string|null $placeholder For template replacement (defaults to parameterName)
     * @param ResolutionConfig|null $resolution null = passthrough, non-null = entity lookup
     */
    public function __construct(
        public string $parameterName,
        public ?string $placeholder = null,
        public ?ResolutionConfig $resolution = null
    ) {
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder ?? $this->parameterName;
    }

    public function isResolutionParameter(): bool
    {
        return $this->resolution !== null;
    }
}
