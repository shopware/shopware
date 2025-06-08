---
title: Fix vue-twig pre-processor
issue: NEXT-18182
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `twig-vue-processor.js` to no longer exchange twig expressions with html comments
* Changed `patches/vue-eslint-parser+9.3.1.patch` to extract twig expressions out of the AST
