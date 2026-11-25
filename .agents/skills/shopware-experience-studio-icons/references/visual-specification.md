# Experience Studio Icon Style

This is the shared visual contract for the element and preset picker illustrations.
The SVG template contains the exact reusable frame, gradients, and shadow.
Change the element-specific content to suit the task; preserve these shared values unless the user requests a style change.

## Canvas and Frame

| Attribute | Value |
|---|---|
| Canvas | `width="200" height="128" viewBox="0 0 200 128"`, transparent outside the frame |
| Outer frame | `x="16" y="14" width="168" height="100" rx="8"` |
| Frame fill | `url(#paper)` |
| Frame stroke | `#9eb3ce`, width `1.2` |
| Stroke defaults | `stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"` |
| Shadow target | Only the outer frame, using `filter="url(#frame-shadow)"` |
| Content centre | `(100, 64)` |
| Content bounds | At most `144 × 76`, inside `x=28..172`, `y=26..102` |

Keep the same enclosing frame on every illustration, including image and slider elements.
Content-specific borders, such as image thumbnails or input fields, can appear inside it.
Do not replace the common frame with one of those inner borders.
Do not add separate shadows to inner shapes.

The surrounding border must stay lighter than the navy content strokes.
Leave enough transparent margin for the shadow to render inside the viewBox.

## Exact Shadow

The `frame-shadow` filter uses `filterUnits="userSpaceOnUse"`, `x="0"`, `y="0"`, `width="200"`, `height="128"`, and `color-interpolation-filters="sRGB"`.
Its single `feDropShadow` has `dx="0"`, `dy="3"`, `stdDeviation="3"`, `flood-color="#24436b"`, and `flood-opacity="0.14"`.
Copy it from the template so its softness and position remain consistent across the family.

## Palette and Gradients

| Role | Colour |
|---|---|
| Content outlines and strong text-shaped paths | `#17265c` |
| Accent blue and active controls | `#1895ff` |
| Muted content lines | `#a7b8cf` |
| Secondary content lines | `#c4d5e8` |
| Image highlights | `#54afff` |
| Frame border | `#9eb3ce` |

Reuse the three template gradients:

- `paper`: `#fff` to `#f3f8ff`, with `x1="30" y1="18" x2="160" y2="114" gradientUnits="userSpaceOnUse"`.
- `wash`: `#eaf5ff` to `#d3eaff`, with `x1="40" y1="30" x2="160" y2="110" gradientUnits="userSpaceOnUse"`.
- `blue`: `#62b8ff` to `#1895ff`, with `x1="0" y1="0" x2="0" y2="1" gradientUnits="objectBoundingBox"`.

Each gradient has its first stop at the start and its second stop at `offset="1"`.
Use additional colours sparingly when the content needs them, such as the YouTube play button or product variant swatches.
Keep the surrounding frame and shadow identical.

## Content Language

Illustrate the recognisable structure of the content: a grid's cells, gallery thumbnails, product cards and carousel controls, a price, review stars, or form fields.
Use a few clear shapes, restrained line detail, rounded controls, pale blue fills, and strong blue accents.

Do not add browser chrome: no window-control dots, address bar, or browser header divider.
Actual content controls remain appropriate, such as a video's playback controls or a tab panel's tabs.
Place them inside the shared frame.

Keep text-like strokes and controls legible when the entire illustration is only `92px` high.
Avoid tiny captions or dense decorative detail.
The picker supplies the element's translated display label, so do not bake that label into the SVG.

For numbers such as prices or ratings, use outlined glyph paths rather than `<text>` or embedded fonts.
The delivered SVG must render correctly on a machine with no fonts installed.
Do not embed raster images, external resources, scripts, event handlers, or `foreignObject` content.
Use local `url(#id)` references only, with each referenced id declared in the SVG.
When assembling multiple icons into one inline SVG preview, prefix every id and its references per icon to prevent collisions.

## Fitting an Existing Motif

Preserve its aspect ratio while centring it inside the content bounds.
For a content bounding box `(x, y, width, height)`, use:

```text
scale = min(144 / width, 76 / height)
translateX = 100 - scale * (x + width / 2)
translateY = 64 - scale * (y + height / 2)
transform="translate(translateX translateY) scale(scale)"
```

Measure the visible content after removing any old surrounding frame or browser toolbar.
Keep the new outer frame outside this transform.
Check the rendered result for stroke clipping and visual balance rather than relying on geometry alone.

## Declaration and Selection

Assets live in `src/Administration/Resources/app/administration/static/img/experience-studio/element-icons/`.
Select an illustration by its filename without the `.svg` extension.
No Administration mapping entry or prefix is needed.
Declare its Meteor icon separately.

Element definition example:

```yaml
meta:
  icon: "regular-image"
  illustration: "media-gallery"
```

Preset definition example:

```yaml
icon: "regular-image-text"
illustration: "preset-media-and-text"
```

The sidebar tree uses `icon` directly.
The picker shows `illustration` when supplied and falls back to `icon` when the SVG cannot load.
An omitted or null illustration shows only the Meteor icon.
If there is no Meteor icon either, the existing generic fallback applies.
Keep this behavior when adding or refining artwork.
