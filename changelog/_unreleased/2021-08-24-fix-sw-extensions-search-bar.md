---
title: Fix sw-extensions search bar
issue: NEXT-15775
---
# Administration
* Changed the placeholder for the Extensions search bar from "Search extensions..." to "Find extensions..." <br>
    * therefore changed the label `placeholderSearchBar` in the snippet files `src/Administration/Resources/app/administration/src/module/sw-extesion/snippet/de-DE.json` and `src/Administration/Resources/app/administration/src/module/sw-extension/snippet/en-GB.json`
* Changed the color of the label in the Extensions search bar from grey to blue
    * therefore added an entity to the in `src/Administration/Resources/app/administration/src/module/sw-extension/index.js` registered module
