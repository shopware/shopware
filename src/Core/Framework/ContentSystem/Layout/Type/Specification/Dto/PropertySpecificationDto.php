<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TranslatableType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedDefault;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedEnum;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
#[TranslatableType]
#[TypedEnum]
#[TypedDefault]
final readonly class PropertySpecificationDto
{
    /**
     * @param list<string|int|float|bool>|null $enum
     * @param array<string, mixed>|null $adminUI
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        #[Assert\NotBlank]
        public string $type,
        public bool $required,
        public bool $translatable,
        #[Assert\NotBlank]
        public string $title,
        #[Assert\NotBlank]
        public string $description,
        public ?array $enum,
        public string|int|float|bool|null $default,
        public ?array $adminUI,
    ) {
    }

    public function toPropertySpecification(): PropertySpecification
    {
        return new PropertySpecification(
            $this->name,
            new PropertyType(
                $this->type,
                $this->translatable,
                $this->enum,
                $this->default,
            ),
            $this->required,
            $this->title,
            $this->description,
            $this->adminUI,
        );
    }
}
