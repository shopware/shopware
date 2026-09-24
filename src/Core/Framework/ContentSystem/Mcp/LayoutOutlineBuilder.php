<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Log\Package;

/**
 * One line per element — `id · component · slot · "label"` — so an agent can address elements by id without
 * reading the stored tree.
 *
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
final class LayoutOutlineBuilder
{
    private const LABEL_PROPERTIES = ['label', 'title', 'headline', 'text', 'videoTitle', 'ariaLabel', 'name'];

    private const LABEL_LENGTH = 60;

    public function build(StoredTree $tree): string
    {
        $lines = [];

        foreach ($tree->roots as $root) {
            array_push($lines, ...$this->lines($root, null, 0));
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $elementIds
     *
     * @return list<string>
     */
    public function describe(StoredTree $tree, array $elementIds): array
    {
        $lines = [];

        foreach ($elementIds as $elementId) {
            $located = $tree->locate($elementId);

            if ($located === null) {
                continue;
            }

            $position = $located['parentId'] === null
                ? \sprintf('root[%d]', $located['index'])
                : \sprintf('%s.%s[%d]', $located['parentId'], $located['slot'], $located['index']);

            $lines[] = $this->line($located['element'], null) . ' · at ' . $position;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function lines(StoredElement $element, ?string $slot, int $depth): array
    {
        $lines = [str_repeat('  ', $depth) . $this->line($element, $slot)];

        foreach ($element->slots as $name => $children) {
            foreach ($children as $child) {
                array_push($lines, ...$this->lines($child, $name, $depth + 1));
            }
        }

        return $lines;
    }

    private function line(StoredElement $element, ?string $slot): string
    {
        $parts = [$element->id, $element->component];

        if ($slot !== null) {
            $parts[] = 'slot ' . $slot;
        }

        $label = $this->label($element);
        if ($label !== null) {
            $parts[] = '"' . $label . '"';
        }

        return implode(' · ', $parts);
    }

    private function label(StoredElement $element): ?string
    {
        foreach (self::LABEL_PROPERTIES as $key) {
            $value = $element->property($key);

            if ($value === null || !$value->isString()) {
                continue;
            }

            $text = trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', $value->asString()))));

            if ($text === '') {
                continue;
            }

            return mb_strlen($text) > self::LABEL_LENGTH ? mb_substr($text, 0, self::LABEL_LENGTH) . '…' : $text;
        }

        return null;
    }
}
