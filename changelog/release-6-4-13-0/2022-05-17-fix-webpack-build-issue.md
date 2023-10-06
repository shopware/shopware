---
title: Fix inconsistent webpack built for storefront JS
issue: NEXT-21598
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Removed `export` inside `Resources/app/storefront/src/utility/modal-extension/pseudo-modal.util.js` from the following const:
    * `PSEUDO_MODAL_CLASS`
    * `PSEUDO_MODAL_TEMPLATE_CLASS`
    * `PSEUDO_MODAL_TEMPLATE_CONTENT_CLASS`
    * `PSEUDO_MODAL_TEMPLATE_TITLE_CLASS`
