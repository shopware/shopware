<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ElementTypeSpecificationDto
{
    /**
     * @param array<string, PropertySpecificationDto> $properties
     * @param list<SlotSpecificationDto> $slots
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $label,
        #[Assert\NotBlank]
        public string $description,
        #[Assert\NotBlank(allowNull: true)]
        public ?string $icon,
        #[Assert\NotBlank(allowNull: true)]
        public ?string $category,
        #[Assert\Valid]
        public CopilotSpecificationDto $copilot,
        #[Assert\Valid]
        public array $properties,
        #[Assert\Valid]
        public array $slots,
    ) {
    }

    public function toContentSystemElementTypeSpecification(string $name, string $source): ContentSystemElementTypeSpecification
    {
        $properties = [];
        foreach ($this->properties as $key => $dto) {
            $properties[$key] = $dto->toPropertySpecification();
        }

        $slots = [];
        foreach ($this->slots as $dto) {
            $slots[] = $dto->toSlotSpecification();
        }

        return new ContentSystemElementTypeSpecification(
            $name,
            $this->label,
            $this->description,
            $this->icon,
            $this->category,
            $this->copilot->toCopilotSpecification(),
            $properties,
            $slots,
            $source,
        );
    }
}
