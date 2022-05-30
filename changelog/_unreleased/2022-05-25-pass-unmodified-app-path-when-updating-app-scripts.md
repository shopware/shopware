---
title: Pass unmodified app path when updating app scripts
issue: NEXT-21763
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Changed `Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister::refresh()` to pass unmodified `$appPath` to the `::updateScripts()` method
