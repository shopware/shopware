---
title: Remove unused variant characteristics placeholder in storefront.
issue: NEXT-16178
---
# Storefront
* Changed `truncate-multiline` mixin in `src/Storefront/Resources/app/storefront/src/scss/abstract/mixins/truncate-multiline.scss` to remove unused characteristics, use line clamp to truncated text at a specific number of lines instead of before and after pseudo.
