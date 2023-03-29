---
title: Escape Comments in Product Comparison Templates
issue: NEXT-23812
author: d.popovic
author_email: darko.popovic@ditegra.de
author_github: dpopov00
---
# Core
* Changed `src\Administration\Resources\app\administration\webpack.config.js` comment removal to escape {#- -#} because it is necessary for correct creation of CSV Product Comparison Templates (because of this comments Twig ignores the trailing newline)
  * old: search: /\{#[\s\S]*?#\}/gm,
  * new: search: /^(?!\{#-)\{#[\s\S]*?#\}/gm (still remove comments, but skip if they are in this combination "{#-" )
