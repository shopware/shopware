<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto;

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
final readonly class LayoutPresetSpecificationDto
{
    /**
     * @param list<array<string, mixed>> $layout
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        #[Assert\NotBlank]
        public string $description,
        #[Assert\NotBlank]
        public string $icon,
        #[Assert\Type(type: 'list', message: 'The "layout" field must be a list of elements.')]
        public array $layout,
    ) {
    }
}
