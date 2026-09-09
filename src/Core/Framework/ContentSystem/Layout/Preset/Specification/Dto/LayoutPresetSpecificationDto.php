<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto;

use Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation\LayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The raw authoring form of a preset, before its `layout` shorthand is compiled into the runtime payload.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[LayoutPresetSpecification]
final readonly class LayoutPresetSpecificationDto
{
    /**
     * @param array<mixed> $layout the authoring layout, validated to be a list by LayoutPresetSpecificationValidator
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        #[Assert\NotBlank]
        public string $description,
        #[Assert\NotBlank]
        public string $icon,
        public array $layout,
    ) {
    }
}
