---
title: Ability to add modal root content
issue: NEXT-40033
---
# Storefront
* Added a modal root template as a placeholder in pseudo-modal.html.twig with the class `js-pseudo-modal-template-root-element`.
* Changed pseudo-modal.util.js so that if the div has the `js-pseudo-modal-template-root-element` class, the modal content is replaced under modal-content, not under modal-body.
