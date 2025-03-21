---
title: Accessibility issue in footer #collapseFooterTitle
issue:
flag: ACCESSIBILITY_TWEAKS
author: Jürgen Hörmann 
author_email: juergen@sfxonline.de
author_github: @jhit
---

# Storefront
* The elements in the footer-navigation are not accessible for screen readers. The `id="collapseFooter{{ loop.index }}"` 
  and respective `class="footer-column-headline ..."` elements are missing the `role` attribute. When using accessibility 
  validation tools they will raise errors like this: [Elements must only use supported ARIA attributes](
  https://dequeuniversity.com/rules/axe/4.10/aria-allowed-attrThis). This change affects the following template
  * `src/Storefront/Resources/views/storefront/layout/footer/footer.html.twig`


