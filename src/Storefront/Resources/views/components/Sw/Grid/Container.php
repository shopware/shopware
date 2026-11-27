<?php declare(strict_types=1);

namespace Shopware\Storefront\Resources\views\components\Sw\Grid;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[Package('framework')]
#[AsTwigComponent]
class Container
{
    public ?string $mode = 'explicit';

    public ?string $itemMinWidth = '400px';

    /**
     * @var array<string, array{columns: list<array{type: string, value?: int|string}>, areas: list<list<string>>}>|null
     */
    public ?array $gridLayout = null;

    public int $gap = 24;

    public ?string $maxWidth = '1400px';

    /**
     * @var array<string, string|null>|string|null
     */
    public array|string|null $padding = null;

    /**
     * @var array<string, string|null>|string|null
     */
    public array|string|null $margin = null;

    public ?string $align = 'normal';

    public ?string $alignContent = 'normal';

    public ?string $justify = 'normal';

    public ?string $justifyContent = 'normal';

    public ?bool $fullWidth = false;

    public ?string $border = null;

    public ?string $borderVariant = null;

    public ?bool $borderFullWidth = false;

    public ?string $borderRadius = '0';

    public ?string $backgroundColor = null;

    public ?int $backgroundOpacity = 100;

    public ?Entity $backgroundImage = null;

    public ?string $backgroundImageMode = 'auto';

    public ?bool $backgroundFullWidth = false;

    public ?int $shadowOffsetX = 0;

    public ?int $shadowOffsetY = 0;

    public ?int $shadowSpread = 0;

    public ?int $shadowBlur = 0;

    public ?int $shadowOpacity = 100;

    public ?bool $shadowInset = false;

    public ?string $shadowColor = '#000000';

    public function __construct(private readonly UrlEncodingTwigFilter $urlEncoding)
    {
    }

    public function mount(?int $gap = 24): void
    {
        $this->gap = $gap ?? 24;
    }

    /**
     * Prepares both grid wrappers and the optional placement rules for the template.
     *
     * @return array{outer: array{class: string, style?: string}, inner: array{class: string, style?: string}, placementRules: string}
     */
    public function getPresentation(string $innerStyle = ''): array
    {
        $sharedClasses = $this->getSharedClasses();
        $outerClasses = ['sw-grid-container', ...$sharedClasses, $this->borderFullWidth ? 'border-full-width' : null];
        $innerClasses = [
            'sw-grid-container__inner',
            ...$sharedClasses,
            $this->alignmentClass('align', $this->align),
            $this->alignmentClass('align-content', $this->alignContent),
            $this->alignmentClass('justify', $this->justify),
            $this->alignmentClass('justify-content', $this->justifyContent),
        ];

        $outerVariables = $this->spacingVariables('margin', $this->margin);
        $innerVariables = $outerVariables + $this->spacingVariables('padding', $this->padding);
        if ($this->gap > 0) {
            $innerVariables['--sw-grid-container-gap'] = $this->gap . 'px';
        }
        if ($this->maxWidth !== null && $this->maxWidth !== '') {
            $innerVariables['--sw-grid-container-max-width'] = $this->maxWidth;
        }

        $placementRules = '';
        switch ($this->mode ?? 'explicit') {
            case 'explicit':
                $pattern = $this->buildGridPattern();
                $innerClasses[] = 'repeated-pattern';
                $innerClasses[] = $pattern['class'];
                $innerVariables += $pattern['variables'];
                $placementRules = $pattern['rules'];

                break;
            case 'auto-fit':
                $innerClasses[] = 'auto-fit';
                $innerVariables['--sw-grid-container-item-min-width'] = $this->itemMinWidth ?? '400px';

                break;
            case 'max-content':
                $innerClasses[] = 'max-content';

                break;
        }

        $background = $this->getBackground();
        if ($this->backgroundFullWidth) {
            array_push($outerClasses, ...$background['classes']);
            $outerVariables += $background['variables'];
        } else {
            array_push($innerClasses, ...$background['classes']);
            $innerVariables += $background['variables'];
        }

        if ($this->borderRadius) {
            $innerClasses[] = 'has-grid-radius';
            $innerVariables['--sw-grid-container-border-radius'] = $this->borderRadius;
        }

        if ($this->shadowColor && $this->shadowOpacity > 0) {
            $innerClasses[] = 'has-grid-shadow';
            $innerVariables['--sw-grid-container-shadow-offsets'] = \sprintf(
                '%s%dpx %dpx %dpx %dpx',
                $this->shadowInset ? 'inset ' : '',
                $this->shadowOffsetX ?? 0,
                $this->shadowOffsetY ?? 0,
                $this->shadowBlur ?? 0,
                $this->shadowSpread ?? 0,
            );
            $innerVariables['--sw-grid-container-shadow-color'] = $this->shadowColor;
            $innerVariables['--sw-grid-container-shadow-opacity'] = $this->shadowOpacity . '%';
        }

        return [
            'outer' => $this->buildAttributes($outerClasses, $outerVariables),
            'inner' => $this->buildAttributes($innerClasses, $innerVariables, $innerStyle),
            'placementRules' => $placementRules,
        ];
    }

    /**
     * @return list<string|null>
     */
    private function getSharedClasses(): array
    {
        return [
            $this->fullWidth ? 'full-width' : null,
            match ($this->border) {
                'all' => 'border',
                'top', 'end', 'bottom', 'start' => 'border-' . $this->border,
                default => null,
            },
            match ($this->borderVariant) {
                'primary', 'secondary' => 'border-' . $this->borderVariant,
                'secondarySubtle' => 'border-secondary-subtle',
                default => null,
            },
        ];
    }

    private function alignmentClass(string $property, ?string $value): ?string
    {
        $suffix = match ($value) {
            'start', 'end', 'center', 'stretch' => $value,
            'spaceAround' => 'space-around',
            'spaceBetween' => 'space-between',
            'spaceEvenly' => 'space-evenly',
            default => null,
        };

        return $suffix === null ? null : $property . '-' . $suffix;
    }

    /**
     * @param array<string, string|null>|string|null $spacing
     *
     * @return array<string, string>
     */
    private function spacingVariables(string $property, array|string|null $spacing): array
    {
        $variable = '--sw-grid-container-' . $property;
        if (!\is_array($spacing)) {
            return $spacing === null || $spacing === '' ? [] : [$variable => $spacing];
        }

        $variables = [];
        foreach (Breakpoint::cases() as $breakpoint) {
            $value = $breakpoint->resolve($spacing);
            if ($value !== null && $value !== '') {
                $variables[$variable . '-' . $breakpoint->value] = $value;
            }
        }

        return $variables;
    }

    /**
     * @return array{classes: list<string>, variables: array<string, string>}
     */
    private function getBackground(): array
    {
        $classes = [];
        $variables = [];
        if ($this->backgroundColor && $this->backgroundOpacity > 0) {
            $classes[] = 'has-grid-background-color';
            $variables['--sw-grid-container-background-color'] = $this->backgroundColor;
            $variables['--sw-grid-container-background-opacity'] = $this->backgroundOpacity . '%';
        }

        $url = $this->urlEncoding->encodeMediaUrl($this->backgroundImage);
        if ($url !== null && $url !== '') {
            $classes[] = 'has-grid-background-image';
            $variables['--sw-grid-container-background-image'] = 'url("' . (new EscaperRuntime())->escape($url, 'css') . '")';

            if (\in_array($this->backgroundImageMode, ['cover', 'contain'], true)) {
                $classes[] = 'grid-background-' . $this->backgroundImageMode;
            }
        }

        return ['classes' => $classes, 'variables' => $variables];
    }

    /**
     * @param list<string|null> $classes
     * @param array<string, string> $variables
     *
     * @return array{class: string, style?: string}
     */
    private function buildAttributes(array $classes, array $variables, string $style = ''): array
    {
        $declarations = '';
        foreach ($variables as $name => $value) {
            $declarations .= $name . ': ' . $value . '; ';
        }

        $attributes = ['class' => implode(' ', array_filter($classes))];
        if ($declarations !== '' || $style !== '') {
            $attributes['style'] = $declarations . $style;
        }

        return $attributes;
    }

    /**
     * @return array{class: string, variables: array<string, string>, rules: string}
     */
    private function buildGridPattern(): array
    {
        $layout = $this->gridLayout ?? [];
        $default = ['columns' => [['type' => 'auto']], 'areas' => [['a1']]];
        $class = 'grid-pattern-' . Hasher::hash([$layout, $this->gap]);
        $variables = [];
        $rules = '';
        foreach (Breakpoint::cases() as $breakpoint) {
            $pattern = $breakpoint->resolve($layout) ?? $default;
            $variables['--sw-grid-columns-' . $breakpoint->value] = $this->buildTracks($pattern['columns']);
            $rules .= $this->buildPlacementRules($class, $breakpoint->value, $this->areaBounds($pattern['areas']));
        }

        return ['class' => $class, 'variables' => $variables, 'rules' => $rules];
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
        // WHY: Auto-placement already puts each visible child into the next single cell, so only spans need rules.
        if (!$this->hasSpanningArea($areas)) {
            return '';
        }

        // WHY: Hidden children must not consume a position in the repeating pattern.
        $displayClasses = '.display-xs, .display-sm, .display-md, .display-lg, .display-xl, .display-xxl';
        $visible = ':is(:not(' . $displayClasses . '), .display-' . $breakpoint . '):not(.d-none):not(.d-' . $breakpoint . '-hidden):not(:has(> .grid-column.d-' . $breakpoint . '-hidden)):not(style)';
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

    /**
     * @param array<string|int, array{column: int, columns: int, rows: int, firstRow: int}> $areas
     */
    private function hasSpanningArea(array $areas): bool
    {
        foreach ($areas as $area) {
            if ($area['columns'] > 1 || $area['rows'] > 1) {
                return true;
            }
        }

        return false;
    }
}
