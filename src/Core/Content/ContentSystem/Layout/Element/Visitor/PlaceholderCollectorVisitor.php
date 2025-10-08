<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Visitor;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class PlaceholderCollectorVisitor implements ElementVisitor
{
    /**
     * @var array<string>
     */
    private array $placeholders = [];

    public function enter(ContentElement $element): void
    {
        foreach ($element->getProperties() as $value) {
            if (\is_string($value)) {
                $this->extractPlaceholders($value);
            }
        }
    }

    public function leave(ContentElement $element): void
    {
    }

    /**
     * @return array<string>
     */
    public function getPlaceholders(): array
    {
        return array_values(array_unique($this->placeholders));
    }

    private function extractPlaceholders(string $input): void
    {
        if (preg_match_all('/\{\{([^}]+)\}\}/', $input, $matches)) {
            foreach ($matches[1] as $placeholder) {
                $this->placeholders[] = $placeholder;
            }
        }
    }
}
