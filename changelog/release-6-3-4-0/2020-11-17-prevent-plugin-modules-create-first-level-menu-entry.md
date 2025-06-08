---
title: Prevent plugin modules to create menu entries on the first level of the main menu
issue: NEXT-8172
---
# Administration
* Added a condition which prevents modules with `type: 'plugin'` and without a `parent` property inside the `navigation` object to register a module entry on the first menu level 
