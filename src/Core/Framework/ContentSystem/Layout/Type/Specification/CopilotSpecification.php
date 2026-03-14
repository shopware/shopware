<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type CopilotSchema = array{summary: string, hints: list<string>}
 */
#[Package('framework')]
final readonly class CopilotSpecification
{
    /**
     * @param list<string> $hints
     */
    public function __construct(
        private string $summary,
        private array $hints,
    ) {
    }

    /**
     * @return CopilotSchema
     */
    public function toSchema(): array
    {
        return [
            'summary' => $this->summary,
            'hints' => $this->hints,
        ];
    }
}
