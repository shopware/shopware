---
title: Copy app snippets after system language change
issue: #11223
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: @fschmtt
---
# Core
* Changed `Shopware\Core\Maintenance\System\Service\SystemLanguageChangeEvent` to include both `$previousLocaleCode` (e.g. `en-GB`) and `$newLocaleCode` (e.g. `en-US`) properties.
___
# Administration
* Added `Shopware\Administration\Framework\App\Subscriber\SystemLanguageChangedSubscriber` to update app admin snippets after a system language change.
