---
title: Update design of header and footer for order documents
issue: NEXT-21255
---
# Core
* Changed some files to update CSS with a new design.
  * `src/Core/Framework/Resources/views/documents/style_base_portrait.css.twig`
  * `src/Core/Framework/Resources/views/documents/style_base_landscape.css.twig`
* Changed some files to change and add a few configs.
  * `src/Core/Framework/Resources/views/documents/includes/footer.html.twig`
  * `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig`
* Changed `src/Core/Framework/Resources/views/documents/base.html.twig` to fix config `pageOrientation`.
* Changed some files to remove `{{ counter.page }}/{{ pages }}` in `headline`
  * `src/Core/Framework/Resources/views/documents/credit_note.html.twig`
  * `src/Core/Framework/Resources/views/documents/delivery_note.html.twig`
  * `src/Core/Framework/Resources/views/documents/invoice.html.twig`
  * `src/Core/Framework/Resources/views/documents/storno.html.twig`
