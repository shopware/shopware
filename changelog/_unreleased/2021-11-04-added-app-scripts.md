---
title: Added app scripts
issue: NEXT-18248
flag: FEATURE_NEXT_17441
---
# Core
* Added `Framework/Script` domain, to introduce scripting feature
* Added `\Shopware\Core\Migration\V6_4\Migration1635237551Script` to add new `script` table
* Added `\Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister` and `\Shopware\Core\Framework\App\Lifecycle\ScriptFileReader` to handle lifecycle of app scripts
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle` and `\Shopware\Core\Framework\App\AppStateService` to manage lifecycle of scripts by apps
