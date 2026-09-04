<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\StructuredPropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TranslatableType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedDefault;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedEnum;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
#[StructuredPropertyType]
#[TranslatableType]
#[TypedEnum]
#[TypedDefault]
final readonly class PropertySpecificationDto
{
    /**
     * @param string|list<string> $type
     * @param list<string|int|float|bool>|null $enum
     * @param array<string, mixed>|null $adminUI
     * @param array<string, self>|null $properties
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        #[Assert\NotBlank]
        public string|array $type,
        public bool $required,
        public bool $translatable,
        #[Assert\NotBlank]
        public string $title,
        #[Assert\NotBlank]
        public string $description,
        public ?array $enum,
        public string|int|float|bool|null $default,
        public ?array $adminUI,
        #[Assert\Valid]
        public ?array $properties = null,
    ) {
    }

    public function toPropertySpecification(): PropertySpecification
    {
        $properties = null;

        if ($this->properties !== null) {
            $properties = [];

            foreach ($this->properties as $key => $property) {
                $properties[$key] = $property->toPropertySpecification();
            }
        }

        return new PropertySpecification(
            $this->name,
            new PropertyType(
                $this->type,
                $this->translatable,
                $this->enum,
                $this->default,
                $properties,
            ),
            $this->required,
            $this->title,
            $this->description,
            $this->adminUI,
        );
    }
}
