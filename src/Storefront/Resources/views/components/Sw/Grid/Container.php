<?php declare(strict_types=1);

namespace Shopware\Storefront\Resources\views\components\Sw\Grid;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * @internal
 */
#[Package('framework')]
#[AsTwigComponent]
class Container
{
    /**
     * @var array<string, array{columns: list<array{type: string, value?: int|string}>, areas: list<list<string>>}>|null
     */
    public ?array $gridLayout = null;

    public int $gap = 24;

    public function mount(?int $gap = 24): void
    {
        $this->gap = $gap ?? 24;
    }

    /**
     * @return array{class: string, style: string, rules: string}
     */
    public function getGridPattern(): array
    {
        $layout = $this->gridLayout ?? ['xs' => ['columns' => [['type' => 'auto']], 'areas' => [['a1']]]];
        $class = 'grid-pattern-' . Hasher::hash([$layout, $this->gap]);
        $style = '';
        $rules = '';
        $pattern = $layout['xs'];
        foreach (['xs', 'sm', 'md', 'lg', 'xl', 'xxl'] as $breakpoint) {
            $pattern = $layout[$breakpoint] ?? $pattern;
            $style .= '--sw-grid-columns-' . $breakpoint . ': ' . $this->buildTracks($pattern['columns']) . '; ';
            $rules .= $this->buildPlacementRules($class, $breakpoint, $this->areaBounds($pattern['areas']));
        }

        return ['class' => $class, 'style' => $style, 'rules' => $rules];
    }

    /**
     * @param list<list<string>> $rows
     *
     * @return array<string|int, array{column: int, columns: int, rows: int, firstRow: int}>
     */
    private function areaBounds(array $rows): array
    {
        $areas = [];
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $cell) {
                if (!isset($areas[$cell])) {
                    $areas[$cell] = ['column' => $columnIndex + 1, 'columns' => 0, 'rows' => 0, 'firstRow' => $rowIndex];
                }
                if ($areas[$cell]['firstRow'] === $rowIndex) {
                    ++$areas[$cell]['columns'];
                }
                if ($areas[$cell]['column'] === $columnIndex + 1) {
                    ++$areas[$cell]['rows'];
                }
            }
        }

        return $areas ?: [['column' => 1, 'columns' => 1, 'rows' => 1, 'firstRow' => 0]];
    }

    /**
     * @param list<array{type: string, value?: int|string}> $columns
     */
    private function buildTracks(array $columns): string
    {
        $widths = [];
        $pixelTotal = 0;
        foreach ($columns as $width) {
            $value = (string) ($width['value'] ?? '');
            $type = $width['type'];
            $valid = \in_array($type, ['px', 'percent', 'fraction'], true)
                && preg_match('/^[1-9][0-9]*\z/', $value)
                && !($type === 'percent' && (int) $value > 100)
                && !($type === 'fraction' && (int) $value > 12);
            $width = ['type' => $valid ? $type : 'auto', 'value' => $valid ? $value : ''];
            if ($width['type'] === 'px') {
                $pixelTotal += (int) $width['value'];
            }
            $widths[] = $width;
        }
        $gapTotal = max(0, $this->gap) * (\count($widths) - 1);
        $tracks = [];
        foreach ($widths as $width) {
            $tracks[] = match ($width['type']) {
                'px' => 'min(' . $width['value'] . 'px, max(0px, calc((100% - ' . $gapTotal . 'px) * ' . $width['value'] . ' / ' . $pixelTotal . ')))',
                'percent', 'fraction' => 'max(0px, calc((100% - ' . ($gapTotal + $pixelTotal) . 'px) * ' . $width['value'] . ' / ' . ($width['type'] === 'percent' ? 100 : 12) . '))',
                default => 'minmax(0, 1fr)',
            };
        }

        return implode(' ', $tracks);
    }

    /**
     * @param array<string|int, array{column: int, columns: int, rows: int, firstRow: int}> $areas
     */
    private function buildPlacementRules(string $class, string $breakpoint, array $areas): string
    {
        $displayClasses = '.display-xs, .display-sm, .display-md, .display-lg, .display-xl, .display-xxl';
        $visible = ':is(:not(' . $displayClasses . '), .display-' . $breakpoint . '):not(.d-' . $breakpoint . '-hidden):not(:has(> .grid-column.d-' . $breakpoint . '-hidden)):not(style)';
        $breakpointRules = '';
        $count = \count($areas);
        $index = 0;
        foreach ($areas as $area) {
            ++$index;
            $breakpointRules .= ':where(.' . $class . ' > :nth-child(' . $count . 'n + ' . $index . ' of ' . $visible . '))'
                . '{--sw-grid-item-column-' . $breakpoint . ':' . $area['column'] . ' / span ' . $area['columns']
                . ';--sw-grid-item-row-' . $breakpoint . ':span ' . $area['rows'] . ';}';
        }

        return $breakpointRules;
    }
}
