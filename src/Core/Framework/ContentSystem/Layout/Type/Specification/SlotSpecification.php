<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type SlotSchema = array{name: string, maxElements: int|null, allowList: list<string>, description: string}
 */
#[Package('framework')]
final readonly class SlotSpecification
{
    /**
     * @param list<string> $allowList
     */
    public function __construct(
        private string $name,
        private ?int $maxElements,
        private array $allowList,
        private string $description,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return SlotSchema
     */
    public function toSchema(): array
    {
        return [
            'name' => $this->name,
            'maxElements' => $this->maxElements,
            'allowList' => $this->allowList,
            'description' => $this->description,
        ];
    }
}
