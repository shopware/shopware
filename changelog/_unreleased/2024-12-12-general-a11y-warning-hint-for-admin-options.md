---
title: General accessibility warning hint for admin options
issue: NEXT-39251
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Administration
* Changed `src/Administration/Resources/app/administration/src/app/component/form/field-base/sw-base-field/index.js` to be able to set a hint via `.xml` file.
* Added a general accessibility hint for the admin option `requireEmailConfirmation` because it is not a good practice to activate this option.
