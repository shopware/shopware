---
title: Removed language dropdown from Settings > Basic Information
issue: NEXT-37559
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: @jozsefdamokos
---
# Administration
* Removed language dropdown from Settings > Basic Information (`sw-settings-basic-information`) because it is not currently supported.
* Deprecated `abortOnLanguageChange`, `saveOnLanguageChange` and `onChangeLanguage` methods in `src/Administration/Resources/app/administration/src/module/sw-settings-basic-information/page/sw-settings-basic-information/index.js`. They will be removed in the next major version.
