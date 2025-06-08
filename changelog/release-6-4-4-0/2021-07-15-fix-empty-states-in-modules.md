---
title: Fix empty states in modules
issue: NEXT-16010
author: Raoul Kramer
author_email: r.kramer@shopware.com 
author_github: @djpogo
---
# Administration
* Fixed styling/visuals of empty module states
* Changed filename `empty-states/customer-empty-state.svg`, was `empty-states/costumer-empty-state.svg`
* Changed default property values `title`, `subline`, `color` and `icon` from `''` to `null` in `sw-empty-state` component
* Changed internal of computed properties `moduleColor()`, `moduleDescription()` and `moduleIcon()` from `return this.… || this.$…` to ` return this.… ?? this.$…`