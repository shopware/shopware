---
title: Fix the component override inheritance in combination with mixins
issue: NEXT-14801
author: Markus Velt
author_email: m.velt@shopware.com 
author_github: @raknison
---
# Administration
* Changed `createComponent` in `vue.adapter.js` to handle the overrides applied to mixins correctly, when a component with a mixins has `Component.overrides` itself.
