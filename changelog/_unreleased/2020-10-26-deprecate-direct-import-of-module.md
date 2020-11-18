---
title: Deprecate direct import of module in administration
issue: NEXT-11619
---
# Administration
* Deprecated webpack alias `module` for `administration/src/module` folder for version 6.4.0.0
___
# Upgrade Information

 Use `import from src/module` instead of `import from 'module'`. However we discourage you to directly use imports of the administration's source in your plugins.
 Use the administration's open API through the global Shopware object.
