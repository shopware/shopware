<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class LayoutPresetSpecificationDtoCollection
{
    /**
     * Keyed by resolved preset id (e.g. "Sw:CategoryPage") so Symfony includes
     * the id in violation property paths: presets[Sw:CategoryPage].layout
     *
     * @param array<string, LayoutPresetSpecificationDto> $presets
     */
    public function __construct(
        #[Assert\Valid]
        public array $presets,
    ) {
    }
}
