# Grid Container

`Sw:Grid:Container` lays out items in Twig templates and Experience Studio YAML presets.
Both examples define three equal-width columns at `lg` and stack the items in one column at `sm`.

## Twig

Use `:gridLayout` to pass the layout and put the items inside the component tag.

```twig
{% set gridLayout = {
    lg: {
        columns: [{ type: 'auto' }, { type: 'auto' }, { type: 'auto' }],
        areas: [['a1', 'a2', 'a3']],
    },
    sm: {
        columns: [{ type: 'auto' }],
        areas: [['a1'], ['a2'], ['a3']],
    },
} %}

<twig:Sw:Grid:Container :gridLayout="gridLayout">
    <twig:Sw:Content:Text text="Left" />
    <twig:Sw:Content:Text text="Middle" />
    <twig:Sw:Content:Text text="Right" />
</twig:Sw:Grid:Container>
```

## YAML presets

Set the grid configuration under `properties` and list the items under `slots.content`.

```yaml
layout:
  - component: Sw:Grid:Container
    properties:
      gridLayout:
        lg:
          columns: [{ type: auto }, { type: auto }, { type: auto }]
          areas: [[a1, a2, a3]]
        sm:
          columns: [{ type: auto }]
          areas: [[a1], [a2], [a3]]
    slots:
      content:
        - component: Sw:Content:Text
          properties: { text: "Left" }
        - component: Sw:Content:Text
          properties: { text: "Middle" }
        - component: Sw:Content:Text
          properties: { text: "Right" }
```

## Modes

Set these options as Twig attributes or under `properties` in YAML.
Only `explicit` uses `gridLayout`.

- `explicit` (default) repeats the columns and areas defined in `gridLayout`.
- `auto-fit` fits as many columns as space allows. Set `mode="auto-fit" itemMinWidth="240px"` in Twig or `mode: auto-fit` and `itemMinWidth: "240px"` in YAML.
- `max-content` sizes each column to its content.

## Explicit layouts

Key `gridLayout` by breakpoint (`xs`, `sm`, `md`, `lg`, `xl`, `xxl`).
Define the base layout under `lg` and add a complete `columns` and `areas` configuration for each breakpoint you want to customize.
List one `columns` entry per track.

| Column type | Width |
| --- | --- |
| `auto` | Shares the remaining space equally with other `auto` columns. Omit `value`. |
| `px` | Sets a target pixel width. Use a positive integer for `value`. |
| `percent` | Uses a percentage of the width available after gaps and pixel columns. Use an integer from 1 to 100. |
| `fraction` | Uses twelfths of the width available after gaps and pixel columns. Use an integer from 1 to 12. |

Each inner `areas` array is a row with one string ID per column.
Use any string IDs, including descriptive names such as `sidebar` and `main`.
Repeat an ID across a rectangle to span cells.
For example, `[[heading, heading], [sidebar, main]]` spans the first row with one area and splits the second row into two.
Visible items fill distinct areas in row order, then repeat the pattern.

An item hidden at a breakpoint does not take an area there.
In a pattern with spanning areas, hide items through the `display` style option, the `display` prop of `Sw:Grid:Column`, or `d-none`.
Responsive Bootstrap display classes such as `d-lg-none` or `d-none d-lg-block` on a direct item are not taken into account there, so the following items can shift by one area.
Patterns without spanning areas support every display class, because the browser places their items.
