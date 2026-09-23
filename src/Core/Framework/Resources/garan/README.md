# Nested GARAN label artwork for mails

Gmail and Outlook for Windows render neither SVG nor `data:` URIs, so the order confirmation mail
attaches the nested GARAN label as an inline PNG (`sw_garan_label_mail` → `GaranLabelInlineImage` →
`GaranLabelMailSubscriber`).

Labels only differ in the guarantee duration, so we keep two images instead of one per duration:

| File | What it is |
|---|---|
| `nested-label-base.png` | The 390x60 label with the duration field left blank |
| `nested-label-numbers.png` | 65x5760 sprite: one 65x60 row per duration, ascending |

`GaranLabelInlineImage::render()` pastes the right row onto the base. The rows cover the durations
`GaranLabelProductValidator` accepts: 30 to 600 months in steps of 6. Both images are twice the 195x30 the
mail displays.

## Regenerating

Needed whenever `src/Core/Framework/Resources/views/garan/nested-label.svg.twig` or the range of valid
durations changes.

Render the template once per duration (`guarantee` set to `GaranLabelDurationFormatter::formatMonths($months)`,
with `GaranLabelTextFitter::fitDurationTextLength()` registered as the `sw_garan_label_duration_text_length`
filter) and screenshot each at 390x60. Stack the `x=10 w=65` field of every render into the sprite, and take
any single render with that field filled white (rows 1 to 58, leaving the border) as the base.

Note:
- The SVG does not embed its font. Drop the `<?xml?>` declaration, put `width="390" height="60"` on the `<svg>`
  element, and wrap it in an HTML page with a white background, no margins, and an `@font-face` for `Inter`
  (`font-weight: 100 900`) pointing at
  `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-Variable-Roman-Latin.woff2`. Without it the
  label renders in a fallback font.
- Screenshot everything in **one** Chrome run (Playwright works), at device scale factor 1, after
  `document.fonts.ready`. Chrome rasterises a few pixels of the calendar icon differently between runs, which
  shows up as a seam between the two images.

`GaranLabelInlineImageTest` should cover the rest: every valid duration renders a 390x60 PNG with its own number, and
composing touches nothing outside the duration field.
